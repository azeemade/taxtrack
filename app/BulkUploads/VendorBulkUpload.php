<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Vendor;
use App\Services\Supplier\SupplierService;
use Illuminate\Support\Str;

class VendorBulkUpload extends BulkUploadAbstract
{
    protected SupplierService $supplierService;
    public function __construct()
    {
        $this->supplierService = app(SupplierService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:vendors,vendor_name,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'reference_id' => 'required|string|max:255|unique:vendors,referenceID,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'email' => 'required|string|max:255|email|unique:vendors,primary_email,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'category' => 'required|string|max:255',
            'currency' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'vat_number' => 'nullable|max:255',
            'vat_date' => 'nullable|string|max:255',
            'tax_type' => 'nullable|string|max:255',
            'industry' => 'required|string|max:255',
            'vendor_type' => 'required|string|in:individual,organization',
            'business_type' => 'required|string|in:limited-liability-partnership,partnership,corporation,sole-proprietorship,limited-company',
            'employee_count' => 'nullable',
            'terms_and_conditions' => 'nullable|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_email' => 'required|string|max:255|email',
            'contact_person_phone_number' => 'required|string|max:255',
            'contact_person_country' => 'required|string|max:255',
            'contact_person_city' => 'required|string|max:255',
            'contact_person_address' => 'required|string|max:255',
            'contact_person_zip_code' => 'required|max:255',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Name',
            'Reference Id',
            'Email',
            'Phone',
            'Address',
            'City',
            'State',
            'Category',
            'Currency',
            'Country',
            'Vendor Type',
            'VAT Number',
            'VAT Date',
            'Tax Type',
            'Industry',
            'Business Type',
            'Employee Count',
            'Terms and Conditions',
            'Contact Person Name',
            'Contact Person Email',
            'Contact Person Phone Number',
            'Contact Person Country',
            'Contact Person City',
            'Contact Person Address',
            'Contact Person Zip Code',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'ABC Suppliers Ltd',
                'REF-001',
                'contact@abcsuppliers.com',
                '+1-555-0456',
                '456 Business Ave',
                'Chicago',
                'IL',
                'Materials',
                'USD',
                'United States',
                // 'individual or organization',
                'organization',
                '1234567890',
                '2025-01-01',
                'standard',
                'Materials',
                'sole-proprietorship',
                '50',
                'Thank you for your business',
                'John Doe',
                'john.doe26@example.com',
                '+1-555-0789',
                'United States',
                'Chicago',
                '456 Business Ave',
                '10001',
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

        if ($validatedData['category']) {
            $category = $this->findCategory($validatedData['category'], 'invoices');
        }

        if ($validatedData['currency']) {
            $currency = $this->findCurrency($validatedData['currency']);
            if (!$currency) {
                $this->addError("Row {$rowNumber}: Currency not found");
                return null;
            }
        }

        if ($validatedData['country']) {
            $country = $this->findCountry($validatedData['country']);
            if (!$country) {
                $this->addError("Row {$rowNumber}: Country not found");
                return null;
            }
        }

        if ($validatedData['city']) {
            $city = $this->findCity($validatedData['city']);
            if (!$city) {
                $this->addError("Row {$rowNumber}: City not found");
                return null;
            }
        }

        if ($validatedData['contact_person_country']) {
            $contactPersonCountry = $this->findCountry($validatedData['contact_person_country']);
        }

        if ($validatedData['contact_person_city']) {
            $contactPersonCity = $this->findCity($validatedData['contact_person_city']);
        }

        try {
            // Prepare data for storage
            $data = [
                'vendor_name' => $validatedData['name'],
                'supplier_reference' => $validatedData['reference_id'],
                'primary_email' => $validatedData['email'],
                'primary_phone_number' => $validatedData['phone'],
                'vendor_type' => $validatedData['vendor_type'] ?? 'individual',
                'primary_address' => $validatedData['address'],
                'city_id' => $city->id,
                'post_code' => $validatedData['zip_code'] ?? null,
                'country_id' => $country->id,
                'vat_number' => $validatedData['vat_number'] ?? null,
                'vat_date' => $validatedData['vat_date'] ?? null,
                'tax_type' => $validatedData['tax_type'] ?? null,
                'industry' => $validatedData['industry'],
                'business_type' => $validatedData['business_type'],
                'employee_count' => $validatedData['employee_count'] ?? null,
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'payment_term' => $validatedData['payment_term'] ?? null,
                'currency_id' => $currency->id,
                'category_id' => $category->id,
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'contact_persons' => [[
                    'full_name' => $validatedData['contact_person_name'] ?? null,
                    'primary_email' => $validatedData['contact_person_email'] ?? null,
                    'secondary_email' => $validatedData['contact_person_secondary_email'] ?? null,
                    'primary_phone_number' => $validatedData['contact_person_phone_number'] ?? null,
                    'secondary_phone_number' => $validatedData['contact_person_secondary_phone_number'] ?? null,
                    'country_id' => $contactPersonCountry->id ?? $country->id ?? null,
                    'city_id' => $contactPersonCity->id ?? $city->id ?? null,
                    'primary_address' => $validatedData['contact_person_primary_address'] ?? null,
                    'secondary_address' => $validatedData['contact_person_secondary_address'] ?? null,
                    'post_code' => $validatedData['contact_person_post_code'] ?? null,
                ]]
            ];

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

    /**
     * Create complex quote record with line items
     * 
     * @param array $data
     * @return \App\Models\Quote|null
     */
    protected function createComplexRecord(array $data)
    {
        if (!isset($data) || !isset($data['contact_persons'])) {
            return null;
        }

        try {

            // Use QuoteService as single source of truth for quote creation
            $supplier = $this->supplierService->createSupplier($data);
            return $supplier;
        } catch (\Exception $e) {
            $this->addError("Failed to create supplier: " . $e->getMessage());
            return null;
        }
    }
}
