<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PurchaseOrder;

class PurchaseOrderBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'purchase_order_number' => 'required|string|max:100',
            'vendor_name' => 'required|string|max:255',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'status' => 'nullable|in:draft,sent,received,completed,cancelled',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Purchase Order Number',
            'Vendor Name',
            'Order Date',
            'Expected Delivery Date',
            'Status',
            'Total Amount',
            'Currency',
            'Notes',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'PO-001',
                'ABC Supplies Ltd',
                '2024-01-15',
                '2024-01-30',
                'draft',
                '1500.00',
                'USD',
                'Office supplies order',
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
            return [
                'purchase_order_number' => $validatedData['purchase_order_number'],
                'vendor_name' => $validatedData['vendor_name'],
                'order_date' => $validatedData['order_date'],
                'expected_delivery_date' => $validatedData['expected_delivery_date'],
                'status' => $validatedData['status'] ?? 'draft',
                'total_amount' => $validatedData['total_amount'],
                'currency' => $validatedData['currency'] ?? 'USD',
                'notes' => $validatedData['notes'],
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
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
}
