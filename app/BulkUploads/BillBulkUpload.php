<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\VendorBill;

class BillBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'bill_number' => 'required|string|max:100',
            'vendor_name' => 'required|string|max:255',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
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
            'Bill Number',
            'Vendor Name',
            'Bill Date',
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
                'BILL-001',
                'ABC Supplies Ltd',
                '2024-01-15',
                '2024-02-15',
                'received',
                '1200.00',
                '96.00',
                'USD',
                'Monthly office supplies bill',
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
                'bill_number' => $validatedData['bill_number'],
                'vendor_name' => $validatedData['vendor_name'],
                'bill_date' => $validatedData['bill_date'],
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
}
