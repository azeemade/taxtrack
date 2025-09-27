<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrder\PurchaseOrderService;

class PurchaseOrderBulkUpload extends BulkUploadAbstract
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct()
    {
        $this->purchaseOrderService = app(PurchaseOrderService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'required|string|max:255',
            'order_date' => 'required|date',
            'order_number' => 'required|string|max:100',
            'terms_and_conditions' => 'nullable|string|max:250',
            'additional_comment' => 'nullable|string|max:250',
            'vat' => 'nullable|numeric|min:0.00',
            'discount' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'status' => 'required|string|in:draft,issued',
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
            'Supplier Name',
            'Supplier Contact',
            'Order Date',
            'Order Number',
            'Terms and Conditions',
            'Additional Comment',
            'VAT',
            'Discount',
            'Additional Charge',
            'Status',
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
                'Wizzy Tech',
                'wizzytech@gmail.com',
                '2024-01-15',
                'PO-001',
                'Thank you for your business',
                'Office supplies order',
                '1.00',
                '3.00',
                '10.00',
                'issued',
                'Office supplies order',
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

            // Check for duplicate purchase order number
            $existingPurchaseOrder = PurchaseOrder::where('company_id', $this->getCurrentCompanyId())
                ->where('purchase_order_no', $validatedData['order_number'])
                ->first();

            if ($existingPurchaseOrder) {
                $this->addWarning("Row {$rowNumber}: Purchase order number '{$validatedData['order_number']}' already exists. The value will be updated");
            }

            // Calculate totals properly
            $unitPrice = $validatedData['item_unit_price'];
            $quantity = $validatedData['item_quantity'] ?? 1;
            $discountPercent = $validatedData['item_discount'] ?? 0;
            $vatPercent = $validatedData['item_vat'] ?? 0;

            // Calculate line item total using QuoteService methods
            $totalUnitPrice = $this->purchaseOrderService->calculateLineItemTotalUnitPrice($unitPrice, $quantity);
            $lineItemTotal = $this->purchaseOrderService->calculateLineItemTotal($totalUnitPrice, $discountPercent, $vatPercent);

            $subtotal = $lineItemTotal;
            $additionalCharge = $validatedData['additional_charge'] ?? 0;
            $total = $this->purchaseOrderService->calculateTotal($subtotal, 0, $additionalCharge);

            if ($validatedData['item_category']) {
                $itemCategory = $this->findCategory($validatedData['item_category'], 'line_items');
            }

            return [
                'vendor_id' => $supplier->id,
                'purchase_order_no' => $validatedData['order_number'],
                'share_status' => 'not-shared',
                'purchase_order_date' => $validatedData['order_date'],
                'additional_charge' => $validatedData['additional_charge'],
                'shipping_charge' => 0,
                'discount' => $validatedData['discount'],
                'vat' => $validatedData['vat'],
                'save_status' => $validatedData['status'],
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'additional_comment' => $validatedData['additional_comment'],
                'sub_total' => $subtotal,
                'purchase_order_value' => $total,
                'currency' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'line_items' => [
                    [
                        'item_details' => $validatedData['item_details'],
                        'category_id' => $itemCategory->id ?? null,
                        'quantity' => $validatedData['item_quantity'],
                        'price' => $validatedData['item_unit_price'],
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
        return PurchaseOrder::class;
    }

    public function getModuleName(): string
    {
        return 'Purchase Order';
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
        return $this->purchaseOrderService->updateOrCreate($data);
    }
}
