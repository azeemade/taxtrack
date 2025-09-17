<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\CreditNote;

class CreditNoteBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'credit_note_number' => 'required|string|max:100',
            'customer_name' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Credit Note Number',
            'Customer Name',
            'Issue Date',
            'Amount',
            'Reason',
            'Notes',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'CN-001',
                'John Doe',
                '2024-01-15',
                '100.00',
                'Product return',
                'Customer returned defective product',
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
                'credit_note_number' => $validatedData['credit_note_number'],
                'customer_name' => $validatedData['customer_name'],
                'issue_date' => $validatedData['issue_date'],
                'amount' => $validatedData['amount'],
                'reason' => $validatedData['reason'],
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
        return CreditNote::class;
    }

    public function getModuleName(): string
    {
        return 'Credit Note';
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
