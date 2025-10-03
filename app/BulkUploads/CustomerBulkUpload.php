<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Customer;
use App\Services\Customer\CustomerService;

class CustomerBulkUpload extends BulkUploadAbstract
{
    protected CustomerService $customerService;
    public function __construct()
    {
        $this->customerService = app(CustomerService::class);
    }


    public function getValidationRules(): array
    {
        return [
            'company_name' => 'required|string|max:255|unique:customers,company_name,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'category' => 'required|string|max:255',
            'customer_id' => 'required|string|max:255|unique:customers,customerID,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'customer_type' => 'required|string|in:individual,organization',
            'business_registration_number' => 'nullable|max:255',
            'vat_number' => 'nullable|max:255',
            'vat_date' => 'nullable|string|max:255',
            'tax_type' => 'nullable|string|max:255',
            'industry' => 'required|string|max:255',
            'business_type' => 'required|string|in:limited-liability-partnership,partnership,corporation,sole-proprietorship,limited-company',
            'employee_count' => 'nullable',
            'currency' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
            'email' => 'required|string|max:255|email|unique:customers,email,NULL,id,company_id,' . $this->getCurrentCompanyId(),
            'country' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'zip_code' => 'nullable|string|max:255',
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
            'Company Name',
            'Category',
            'Customer ID',
            'Customer Type',
            'Business Registration Number',
            'VAT Number',
            'VAT Date',
            'Tax Type',
            'Industry',
            'Business Type',
            'Employee Count',
            'Currency',
            'Phone Number',
            'Email',
            'Country',
            'City',
            'Company Address',
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
                'Bakir Industries',
                'Services',
                'CUST-001',
                'individual or organization',
                '1234567890',
                '1234567890',
                '2025-01-01',
                'standard',
                'Technology',
                'sole-proprietorship or partnership or corporation',
                '50',
                'USD',
                '+1-555-0123',
                'john.doe25@example.com',
                'United States',
                'New York',
                '123 Main Street',
                '',
                'Pelumi John',
                'pelumi.john@example.com',
                '+1-555-0456',
                'United States',
                'New York',
                '123 Main Street',
                '10001',
            ]
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
                'company_name' => $validatedData['company_name'],
                'email' => $validatedData['email'],
                'customerID' => $validatedData['customerID'] ?? null,
                'business_registration_number' => $validatedData['business_registration_number'] ?? null,
                'category_id' => $category->id ?? null,
                'customer_type' => $validatedData['customer_type'] ?? 'individual',
                'business_type' => $validatedData['business_type'] ?? null,
                'industry' => $validatedData['industry'] ?? null,
                'employee_count' => $validatedData['employee_count'] ?? 0,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId(),
                'phone_number' => $validatedData['phone_number'],
                'current_balance' => $validatedData['current_balance'] ?? 0.00,
                'payment_term' => $validatedData['payment_term'] ?? null,
                'currency_id' => $currency->id,
                'country_id' => $country->id,
                'city_id' => $city->id,
                'vat_date' => $validatedData['vat_date'] ?? null,
                'tax_type' => $validatedData['tax_type'] ?? null,
                'vat_number' => $validatedData['vat_number'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'zip_code' => $validatedData['zip_code'] ?? null,
                'special_instruction' => $validatedData['special_instruction'] ?? null,
                'terms_and_conditions' => $validatedData['terms_and_conditions'] ?? null,
                'customer_logo' => $validatedData['customer_logo'] ?? null,
                'statement_document_link' => $validatedData['statement_document_link'] ?? null,
                'is_active' => $validatedData['is_active'] ?? true,
                'phone_ext' => $validatedData['phone_ext'] ?? '',
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
        return Customer::class;
    }

    public function getModuleName(): string
    {
        return 'Customer';
    }

    public function getValidationMessages(): array
    {
        return array_merge(parent::getValidationMessages(), [
            'name.required' => 'Customer name is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'A customer with this email already exists.',
            'phone.string' => 'Phone must be a valid phone number.',
        ]);
    }

    public function getMaxRows(): int
    {
        return 500; // Customers can have more complex data, so lower limit
    }

    public function shouldProcessAsync(): bool
    {
        return true; // Always process customers asynchronously due to potential duplicates
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
            $customer = $this->customerService->createCustomer($data);
            return $customer;
        } catch (\Exception $e) {
            $this->addError("Failed to create customer: " . $e->getMessage());
            return null;
        }
    }
}
