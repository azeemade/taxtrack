<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PurchaseInvoice;

class PurchaseInvoiceBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'invoice_number' => 'required|string|max:100',
            'vendor_name' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'status' => 'nullable|in:draft,received,paid,overdue',
            'total_amount' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Invoice Number',
            'Vendor Name',
            'Invoice Date',
            'Due Date',
            'Status',
            'Total Amount',
            'Tax Amount',
            'Currency',
            'Notes',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'INV-001',
                'ABC Supplies Ltd',
                '2024-01-15',
                '2024-02-15',
                'received',
                '1500.00',
                '120.00',
                'USD',
                'Office supplies invoice',
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
                'invoice_number' => $validatedData['invoice_number'],
                'vendor_name' => $validatedData['vendor_name'],
                'invoice_date' => $validatedData['invoice_date'],
                'due_date' => $validatedData['due_date'],
                'status' => $validatedData['status'] ?? 'received',
                'total_amount' => $validatedData['total_amount'],
                'tax_amount' => $validatedData['tax_amount'] ?? 0,
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
}
