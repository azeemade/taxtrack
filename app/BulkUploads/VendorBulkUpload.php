<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Vendor;
use Illuminate\Support\Str;

class VendorBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:vendors,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'vendor_type' => 'nullable|in:supplier,contractor,service_provider',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Address',
            'City',
            'State',
            'Postal Code',
            'Country',
            'Tax Number',
            'Vendor Type',
            'Payment Terms',
            'Credit Limit',
            'Notes',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'ABC Supplies Ltd',
                'contact@abcsupplies.com',
                '+1-555-0456',
                '456 Business Ave',
                'Chicago',
                'IL',
                '60601',
                'United States',
                'TAX987654321',
                'supplier',
                'Net 15',
                '10000.00',
                'Reliable supplier for office equipment',
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        // Validate the row first
        $validatedData = $this->validateRow($row, $rowNumber);

        if ($validatedData === false) {
            return null;
        }

        try {
            // Prepare data for storage
            $data = [
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'address' => $validatedData['address'],
                'city' => $validatedData['city'],
                'state' => $validatedData['state'],
                'postal_code' => $validatedData['postal_code'],
                'country' => $validatedData['country'],
                'tax_number' => $validatedData['tax_number'],
                'vendor_type' => $validatedData['vendor_type'] ?? 'supplier',
                'payment_terms' => $validatedData['payment_terms'],
                'credit_limit' => $validatedData['credit_limit'] ?? 0.00,
                'notes' => $validatedData['notes'],
                'slug' => Str::slug($validatedData['name']),
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];

            // Check for duplicate email within the same company
            if (!empty($data['email'])) {
                $existingVendor = Vendor::where('company_id', $data['company_id'])
                    ->where('email', $data['email'])
                    ->first();

                if ($existingVendor) {
                    $this->addError("Row {$rowNumber}: Vendor with email '{$data['email']}' already exists");
                    return null;
                }
            }

            // Check for duplicate name within the same company
            $existingVendor = Vendor::where('company_id', $data['company_id'])
                ->where('name', $data['name'])
                ->first();

            if ($existingVendor) {
                $this->addWarning("Row {$rowNumber}: Vendor with name '{$data['name']}' already exists - will be updated");
            }

            return $data;
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return Vendor::class;
    }

    public function getModuleName(): string
    {
        return 'Vendor';
    }

    public function getValidationMessages(): array
    {
        return array_merge(parent::getValidationMessages(), [
            'name.required' => 'Vendor name is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'A vendor with this email already exists.',
            'credit_limit.numeric' => 'Credit limit must be a number.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
        ]);
    }

    public function getMaxRows(): int
    {
        return 500;
    }

    public function shouldProcessAsync(): bool
    {
        return true;
    }
}
