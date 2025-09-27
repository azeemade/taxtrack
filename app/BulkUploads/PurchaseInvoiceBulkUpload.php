<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\PurchaseInvoice\PurchaseInvoiceService;

class PurchaseInvoiceBulkUpload extends BulkUploadAbstract
{
    protected PurchaseInvoiceService $purchaseInvoiceService;

    public function __construct()
    {
        $this->purchaseInvoiceService = app(PurchaseInvoiceService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'terms_and_conditions' => 'nullable|string|max:250',
            'additional_comment' => 'nullable|string|max:250',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'status' => 'nullable|in:draft,issued',
            'purchase_order_number' => 'nullable|string|max:100',
            'item_details' => 'required|string|max:50',
            'item_category' => 'nullable|string',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_discount' => 'nullable|numeric|min:0|max:100',
            'item_vat' => 'nullable|numeric|min:0|max:100',
            'item_unit_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3'
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Supplier Name',
            'Supplier Contact',
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Terms and Conditions',
            'Additional Comment',
            'Additional Charge',
            'Status',
            'Purchase Order Number',
            'Item Details',
            'Item Category',
            'Item Quantity',
            'Item Discount',
            'Item VAT',
            'Item Unit Price',
            'Currency'
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'ABC Supplies Ltd',
                'contact@abcsupplies.com',
                'INV-0234',
                '2025-01-15',
                '2025-02-15',
                'Thank you for your business',
                'Office supplies invoice',
                '10.00',
                'issued',
                '',
                'Office supplies invoice',
                'Supplies',
                '10',
                '50.00',
                '8.5',
                '100.00',
                'USD',
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        $validatedData = $this->validateRow($row, $rowNumber);

        if ($validatedData === false) {
            return null;
        }


        try {
            // Find or create customer (reuse logic from InvoiceBulkUpload)
            $supplier = $this->findOrCreateSupplier($validatedData, $rowNumber);

            if (!$supplier) {
                return null;
            }
            $currency = $this->findCurrency($validatedData['currency']);

            // Check for duplicate purchase invoice number
            $existingPurchaseInvoice = PurchaseInvoice::where('company_id', $this->getCurrentCompanyId())
                ->where('purchase_invoiceID', $validatedData['invoice_number'])
                ->first();

            if ($existingPurchaseInvoice) {
                $this->addWarning("Row {$rowNumber}: Purchase invoice number '{$validatedData['invoice_number']}' already exists. The value will be updated");
            }

            if ($validatedData['purchase_order_number']) {
                $purchaseOrder = PurchaseOrder::where('company_id', $this->getCurrentCompanyId())
                    ->where('purchase_order_no', $validatedData['purchase_order_number'])
                    ->first();
            }

            // Calculate totals properly
            $unitPrice = $validatedData['item_unit_price'];
            $quantity = $validatedData['item_quantity'] ?? 1;
            $discountPercent = $validatedData['item_discount'] ?? 0;
            $vatPercent = $validatedData['item_vat'] ?? 0;

            // Calculate line item total using QuoteService methods
            $totalUnitPrice = $this->purchaseInvoiceService->calculateLineItemTotalUnitPrice($unitPrice, $quantity);
            $lineItemTotal = $this->purchaseInvoiceService->calculateLineItemTotal($totalUnitPrice, $discountPercent, $vatPercent);

            $subtotal = $lineItemTotal;
            $additionalCharge = $validatedData['additional_charge'] ?? 0;
            $total = $this->purchaseInvoiceService->calculateTotal($subtotal, 0, $additionalCharge);

            if ($validatedData['item_category']) {
                $itemCategory = $this->findCategory($validatedData['item_category'], 'line_items');
            }

            return [
                'vendor_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder->id ?? null,
                'purchase_order_due_date' => $purchaseOrder->purchase_order_date ?? null,
                'purchase_invoiceID' => $validatedData['invoice_number'],
                'invoice_start_date' => $validatedData['invoice_date'],
                'invoice_end_date' => $validatedData['due_date'],
                'share_status' => 'not-shared',
                'sub_total' => $subtotal,
                'shipping_charge' => 0,
                'additional_charge' => $additionalCharge,
                'discount' => $validatedData['item_discount'],
                'vat' => $validatedData['item_vat'],
                'tax' => $validatedData['tax_amount'] ?? 0,
                'purchase_invoices_total' => $total,
                'save_status' => $validatedData['status'] ?? 'issued',
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'currency' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'line_items' => [
                    [
                        'item_details' => $validatedData['item_details'],
                        'category_id' => $itemCategory->id ?? null,
                        'quantity' => $validatedData['item_quantity'],
                        'price' => $validatedData['item_unit_price'],
                        'discount' => $validatedData['item_discount'],
                        'vat' => $validatedData['item_vat'],
                        'amount' => $validatedData['item_unit_price'] * $validatedData['item_quantity'],
                    ]
                ]
            ];
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return PurchaseInvoice::class;
    }

    public function getModuleName(): string
    {
        return 'Purchase Invoice';
    }

    public function getMaxRows(): int
    {
        return 300;
    }

    public function shouldProcessAsync(): bool
    {
        return true;
    }

    protected function createComplexRecord(array $data)
    {
        return $this->purchaseInvoiceService->updateOrCreate($data);
    }
}
