<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\PaymentMethod;

class PaymentMethodBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:cash,bank_transfer,credit_card,debit_card,check,other',
            'account_number' => 'nullable|string|max:100',
            'routing_number' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Name',
            'Type',
            'Account Number',
            'Routing Number',
            'Bank Name',
            'Is Active',
            'Notes',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'Business Checking Account',
                'bank_transfer',
                '1234567890',
                '021000021',
                'Chase Bank',
                '1',
                'Primary business checking account',
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
                'name' => $validatedData['name'],
                'type' => $validatedData['type'],
                'account_number' => $validatedData['account_number'],
                'routing_number' => $validatedData['routing_number'],
                'bank_name' => $validatedData['bank_name'],
                'is_active' => $validatedData['is_active'] ?? true,
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
        return PaymentMethod::class;
    }

    public function getModuleName(): string
    {
        return 'Payment Method';
    }

    public function getMaxRows(): int
    {
        return 200;
    }

    public function shouldProcessAsync(): bool
    {
        return false; // Payment methods are simple, can be processed synchronously
    }
}
