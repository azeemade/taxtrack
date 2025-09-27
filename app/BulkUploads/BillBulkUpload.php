<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PurchaseInvoice;
use App\Models\VendorBill;
use App\Services\Bills\BillsService;
use App\Services\PurchaseInvoice\PurchaseInvoiceService;

class BillBulkUpload extends BulkUploadAbstract
{
    protected PurchaseInvoiceService $purchaseInvoiceService;
    protected BillsService $billsService;
    public function __construct()
    {
        $this->purchaseInvoiceService = app(PurchaseInvoiceService::class);
        $this->billsService = app(BillsService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'purchase_invoice_number' => 'required|string|exists:purchase_invoices,purchase_invoiceID,company_id,' . $this->getCurrentCompanyId(),
            'bill_number' => 'required|string|max:100',
            'due_date' => 'required|date',
            'terms_and_conditions' => 'nullable|string|max:250',
            'additional_comment' => 'nullable|string|max:250',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'status' => 'nullable|in:draft,issued',
            'payment_status' => 'nullable|in:pending,partial-payment,full-payment',
            'item_details' => 'required|string|max:50',
            'item_category' => 'nullable|string',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_discount' => 'nullable|numeric|min:0|max:100',
            'item_vat' => 'nullable|numeric|min:0|max:100',
            'item_unit_price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Purchase Invoice Number',
            'Bill Number',
            'Due Date',
            'Terms and Conditions',
            'Additional Comment',
            'Additional Charge',
            'Status',
            'Payment Status',
            'Item Details',
            'Item Category',
            'Item Quantity',
            'Item Discount',
            'Item VAT',
            'Item Unit Price',
            'Currency',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'PID-0004',
                'BILL-001',
                '2026-01-15',
                'Thank you for your business',
                'Office supplies bill',
                '10.00',
                'issued',
                'pending',
                'Office supplies bill',
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
            $purchaseInvoice = PurchaseInvoice::where('company_id', $this->getCurrentCompanyId())
                ->where('purchase_invoiceID', $validatedData['purchase_invoice_number'])
                ->first();

            if (!$purchaseInvoice) {
                $this->addError("Row {$rowNumber}: Purchase invoice number '{$validatedData['purchase_invoice_number']}' not found");
                return null;
            }


            // Check for duplicate quote number
            $existingBill = VendorBill::where('company_id', $this->getCurrentCompanyId())
                ->where('vendor_billID', $validatedData['bill_number'])
                ->first();

            if ($existingBill) {
                $this->addWarning("Row {$rowNumber}: Bill number '{$validatedData['bill_number']}' already exists. The value will be updated");
            }


            // Calculate totals properly
            $unitPrice = $validatedData['item_unit_price'];
            $quantity = $validatedData['item_quantity'] ?? 1;
            $discountPercent = $validatedData['item_discount'] ?? 0;
            $vatPercent = $validatedData['item_vat'] ?? 0;

            // Calculate line item total using QuoteService methods
            $totalUnitPrice = $this->purchaseInvoiceService->calculateLineItemTotalUnitPrice($unitPrice, $quantity);
            $lineItemTotal = $this->purchaseInvoiceService->calculateLineItemTotal($totalUnitPrice, $discountPercent, $vatPercent);

            $subtotal = $lineItemTotal + ($existingBill?->lineItems->sum('amount') ?? 0);
            $additionalCharge = $validatedData['additional_charge'] ?? 0;
            $total = $this->purchaseInvoiceService->calculateTotal($subtotal, 0, $additionalCharge);

            if ($validatedData['item_category']) {
                $itemCategory = $this->findCategory($validatedData['item_category'], 'line_items');
            }

            return [
                'vendor_id' => $purchaseInvoice->vendor_id,
                'purchase_invoice_id' => $purchaseInvoice->id,
                'purchase_invoice_due_date' => $purchaseInvoice->invoice_end_date,
                'share_status' => 'not-shared',
                'vendor_billID' => $validatedData['bill_number'],
                'vendor_bill_due_date' => $validatedData['due_date'],
                'sub_total' => $subtotal,
                'shipping_charge' => 0,
                'additional_charge' => $validatedData['additional_charge'] ?? 0,
                'discount' => $validatedData['item_discount'] ?? 0,
                'vat' => $validatedData['item_vat'] ?? 0,
                'vendor_bill_total' => $total,
                'save_status' => $validatedData['status'] ?? 'issued',
                'payment_status' => $validatedData['payment_status'] ?? 'pending',
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'additional_comment' => $validatedData['additional_comment'],
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'line_items' => [
                    ...($existingBill?->lineItems->toArray() ?? []),
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
        return VendorBill::class;
    }

    public function getModuleName(): string
    {
        return 'Bill';
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
        return $this->billsService->updateOrCreate($data);
    }
}
