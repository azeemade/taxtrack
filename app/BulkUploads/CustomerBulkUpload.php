<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Http\Requests\Company\Sales\Customer\CreateIndividualRequest;
use App\Http\Requests\Company\Sales\Customer\CreateOrganizationRequest;
use App\Models\Customer;
use App\Services\RequestValidationService;
use Illuminate\Support\Str;

class CustomerBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        // Default to individual customer rules
        return RequestValidationService::extractRules(CreateIndividualRequest::class);
    }

    public function getValidationRulesForType(string $type): array
    {
        return match ($type) {
            'individual' => RequestValidationService::extractRules(CreateIndividualRequest::class),
            'organization' => RequestValidationService::extractRules(CreateOrganizationRequest::class),
            default => RequestValidationService::extractRules(CreateIndividualRequest::class),
        };
    }

    public function getAvailableTemplateTypes(): array
    {
        return [
            'individual' => 'Individual Customer',
            'organization' => 'Organization Customer',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        $type = $subType ?? 'individual';
        $rules = $this->getValidationRulesForType($type);

        return RequestValidationService::rulesToHeaders($rules);
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        $type = $subType ?? 'individual';
        $rules = $this->getValidationRulesForType($type);

        return RequestValidationService::rulesToSampleData($rules);
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
                'customer_type' => $validatedData['customer_type'] ?? 'individual',
                'credit_limit' => $validatedData['credit_limit'] ?? 0.00,
                'payment_terms' => $validatedData['payment_terms'],
                'notes' => $validatedData['notes'],
                'slug' => Str::slug($validatedData['name']),
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];

            // Check for duplicate email within the same company
            if (!empty($data['email'])) {
                $existingCustomer = Customer::where('company_id', $data['company_id'])
                    ->where('email', $data['email'])
                    ->first();

                if ($existingCustomer) {
                    $this->addError("Row {$rowNumber}: Customer with email '{$data['email']}' already exists");
                    return null;
                }
            }

            // Check for duplicate name within the same company
            $existingCustomer = Customer::where('company_id', $data['company_id'])
                ->where('name', $data['name'])
                ->first();

            if ($existingCustomer) {
                $this->addWarning("Row {$rowNumber}: Customer with name '{$data['name']}' already exists - will be updated");
            }

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
            'credit_limit.numeric' => 'Credit limit must be a number.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
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
     * Prepare individual customer data
     */
    protected function prepareIndividualCustomerData(array $validatedData, int $rowNumber): array
    {
        return [
            'full_name' => $validatedData['full_name'] ?? '',
            'display_name' => $validatedData['display_name'] ?? '',
            'salutation' => $validatedData['salutation'] ?? '',
            'category_id' => $validatedData['category_id'] ?? null,
            'customer_type' => 'individual',
            'currency_id' => $validatedData['currency_id'] ?? 1,
            'image' => $validatedData['image'] ?? null,
            'phone_ext' => $validatedData['phone_ext'] ?? '',
            'primary_phone_number' => $validatedData['primary_phone_number'] ?? '',
            'secondary_phone_number' => $validatedData['secondary_phone_number'] ?? '',
            'primary_email' => $validatedData['primary_email'] ?? '',
            'secondary_email' => $validatedData['secondary_email'] ?? '',
            'country_id' => $validatedData['country_id'] ?? null,
            'city_id' => $validatedData['city_id'] ?? null,
            'primary_address' => $validatedData['primary_address'] ?? '',
            'secondary_address' => $validatedData['secondary_address'] ?? '',
            'zip_code' => $validatedData['zip_code'] ?? '',
            'contact_person_id' => $validatedData['contact_person_id'] ?? null,
            'company_id' => $this->getCurrentCompanyId(),
            'created_by' => $this->getCurrentUserId(),
            'edited_by' => $this->getCurrentUserId(),
        ];
    }

    /**
     * Prepare organization customer data
     */
    protected function prepareOrganizationCustomerData(array $validatedData, int $rowNumber): array
    {
        return [
            'company_name' => $validatedData['company_name'] ?? '',
            'primary_email' => $validatedData['primary_email'] ?? '',
            'primary_phone_number' => $validatedData['primary_phone_number'] ?? '',
            'business_registration_number' => $validatedData['business_registration_number'] ?? '',
            'vat_number' => $validatedData['vat_number'] ?? '',
            'industry' => $validatedData['industry'] ?? '',
            'business_type' => $validatedData['business_type'] ?? '',
            'employee_count' => $validatedData['employee_count'] ?? 0,
            'image' => $validatedData['image'] ?? null,
            'special_instruction' => $validatedData['special_instruction'] ?? '',
            'payment_term' => $validatedData['payment_term'] ?? null,
            'currency_id' => $validatedData['currency_id'] ?? 1,
            'phone_ext' => $validatedData['phone_ext'] ?? '',
            'address' => $validatedData['address'] ?? '',
            'country_id' => $validatedData['country_id'] ?? null,
            'city_id' => $validatedData['city_id'] ?? null,
            'state_id' => $validatedData['state_id'] ?? null,
            'contact_persons' => $validatedData['contact_persons'] ?? [],
            'company_id' => $this->getCurrentCompanyId(),
            'created_by' => $this->getCurrentUserId(),
            'edited_by' => $this->getCurrentUserId(),
        ];
    }

    /**
     * Check for duplicate customers
     */
    protected function checkForDuplicates(array $data, int $rowNumber): void
    {
        $companyId = $this->getCurrentCompanyId();

        // Check for duplicate email
        if (!empty($data['primary_email'])) {
            $existingCustomer = Customer::where('company_id', $companyId)
                ->where('primary_email', $data['primary_email'])
                ->first();

            if ($existingCustomer) {
                $this->addError("Row {$rowNumber}: Customer with email '{$data['primary_email']}' already exists");
                return;
            }
        }

        // Check for duplicate company name or display name
        $nameField = $data['company_name'] ?? $data['display_name'] ?? '';
        if (!empty($nameField)) {
            $existingCustomer = Customer::where('company_id', $companyId)
                ->where(function ($query) use ($nameField) {
                    $query->where('company_name', $nameField)
                        ->orWhere('display_name', $nameField);
                })
                ->first();

            if ($existingCustomer) {
                $this->addWarning("Row {$rowNumber}: Customer with name '{$nameField}' already exists - will be updated");
            }
        }
    }
}
