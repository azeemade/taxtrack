<?php

namespace App\Abstracts;

use App\Contracts\BulkUploadContract;
use App\Models\Category;
use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Nnjeim\World\Models\Currency;

abstract class BulkUploadAbstract implements BulkUploadContract
{
    protected array $errors = [];
    protected array $warnings = [];
    protected int $processedRows = 0;
    protected int $successfulRows = 0;
    protected int $failedRows = 0;
    protected ?int $userId = null;
    protected ?int $companyId = null;

    /**
     * Default validation messages
     */
    public function getValidationMessages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'numeric' => 'The :attribute must be a number.',
            'date' => 'The :attribute must be a valid date.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
        ];
    }

    /**
     * Default maximum rows
     */
    public function getMaxRows(): int
    {
        return 1000;
    }

    /**
     * Default to async processing
     */
    public function shouldProcessAsync(): bool
    {
        return true;
    }

    /**
     * Get available template subtypes for this module
     * Default implementation returns empty array (single template)
     *
     * @return array
     */
    public function getAvailableTemplateTypes(): array
    {
        return [];
    }

    /**
     * Validate a single row
     *
     * @param array $row
     * @param int $rowNumber
     * @return array|false Returns validated data or false if validation fails
     */
    protected function validateRow(array $row, int $rowNumber): array|false
    {
        try {
            $validator = Validator::make($row, $this->getValidationRules(), $this->getValidationMessages());

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->errors[] = "Row {$rowNumber}: {$error}";
                }
                $this->failedRows++;
                return false;
            }

            return $validator->validated();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->errors[] = "Row {$rowNumber}: {$message}";
                }
            }
            $this->failedRows++;
            return false;
        }
    }

    /**
     * Add a warning message
     *
     * @param string $message
     */
    protected function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Add an error message
     *
     * @param string $message
     */
    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Get all errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings
     *
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get processing statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'processed_rows' => $this->processedRows,
            'successful_rows' => $this->successfulRows,
            'failed_rows' => $this->failedRows,
            'total_errors' => count($this->errors),
            'total_warnings' => count($this->warnings),
        ];
    }

    /**
     * Reset statistics
     */
    protected function resetStatistics(): void
    {
        $this->processedRows = 0;
        $this->successfulRows = 0;
        $this->failedRows = 0;
        $this->errors = [];
        $this->warnings = [];
    }

    /**
     * Increment processed rows
     */
    public function incrementProcessedRows(): void
    {
        $this->processedRows++;
    }

    /**
     * Increment successful rows
     */
    public function incrementSuccessfulRows(): void
    {
        $this->successfulRows++;
    }

    /**
     * Increment failed rows
     */
    public function incrementFailedRows(): void
    {
        $this->failedRows++;
    }

    /**
     * Check if a value is empty (null, empty string, or whitespace only)
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_string($value) && trim($value) === '');
    }

    /**
     * Set user context for bulk upload processing
     *
     * @param int $userId
     * @param int $companyId
     * @return void
     */
    public function setUserContext(int $userId, int $companyId): void
    {
        $this->userId = $userId;
        $this->companyId = $companyId;
    }

    /**
     * Get current user ID
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
        if ($this->userId !== null) {
            return $this->userId;
        }

        if (Auth::check()) {
            return Auth::id();
        }
        return null;
    }

    /**
     * Get current company ID
     *
     * @return int|null
     */
    protected function getCurrentCompanyId(): ?int
    {
        if ($this->companyId !== null) {
            return $this->companyId;
        }

        if (Auth::check()) {
            $user = Auth::user();
            return $user->current_company_id ?? null;
        }
        return null;
    }

    /**
     * Get current company currency
     *
     * @return \Nnjeim\World\Models\Currency|null
     */
    protected function getCurrentCompanyCurrency(): ?Currency
    {
        if ($this->companyId !== null) {
            $company = \App\Models\Company::find($this->companyId);
            return $company?->currentCurrency();
        }

        if (Auth::check()) {
            return Auth::user()->company->currentCurrency();
        }
        return null;
    }

    /**
     * Find Category
     * 
     * @param string $category
     * @param string $table
     * @return \App\Models\Category|null
     */
    protected function findCategory(string $categoryName, string $table): ?Category
    {
        $category = Category::where('name', $categoryName)
            ->where('table', $table)->first();

        if (!$category) {
            $category = Category::create([
                'name' => $categoryName,
                'table' => $table,
            ]);
        }
        return $category;
    }

    /**
     * Process the prepared data and create the actual records
     * This method should be called after all rows have been processed
     * 
     * @param array $processedData
     * @return array
     */
    public function processPreparedData(array $processedData): array
    {
        $createdRecords = [];
        $errors = [];

        foreach ($processedData as $index => $data) {
            try {
                $record = $this->createRecord($data);
                if ($record) {
                    $createdRecords[] = $record;

                    // Perform post-creation operations
                    $this->performPostCreationOperations($record, $data);
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to create record at index {$index}: " . $e->getMessage();
                $this->addError("Failed to create record at index {$index}: " . $e->getMessage());
            }
        }

        return [
            'created_records' => $createdRecords,
            'errors' => $errors,
            'total_created' => count($createdRecords),
            'total_failed' => count($errors)
        ];
    }

    /**
     * Create a single record from prepared data
     * Override this method in child classes for custom creation logic
     * 
     * @param array $data
     * @return mixed|null
     */
    protected function createRecord(array $data)
    {
        $modelClass = $this->getModelClass();

        // If data contains the main model data directly
        // if (isset($data['invoice']) || isset($data['quote']) || isset($data['customer'])) {
        // Handle complex data structures like invoices with line items
        return $this->createComplexRecord($data);
        // }

        // Handle simple data structures
        // return $modelClass::create($data);
    }

    /**
     * Create complex records (like invoices with line items)
     * Override this method in child classes for custom complex creation logic
     * 
     * @param array $data
     * @return mixed|null
     */
    protected function createComplexRecord(array $data)
    {
        // Default implementation - should be overridden by child classes
        return null;
    }

    /**
     * Perform operations after record creation (like adding line items, sending emails, etc.)
     * Override this method in child classes for custom post-creation logic
     * 
     * @param mixed $record
     * @param array $data
     * @return void
     */
    protected function performPostCreationOperations($record, array $data): void
    {
        // Default implementation - should be overridden by child classes
        // This is where you would add line items, send emails, etc.
    }

    /**
     * Find or create customer
     */
    protected function findOrCreateCustomer(array $data, int $rowNumber): ?Customer
    {
        $companyId = $this->getCurrentCompanyId();

        // Try to find by email first
        if (!empty($data['customer_contact'])) {
            $customer = Customer::where('company_id', $companyId)
                ->where(function ($query) use ($data) {
                    $query->where('email', $data['customer_contact'])
                        ->orWhere('phone_number', $data['customer_contact']);
                })
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        // Try to find by name
        $customer = Customer::where('company_id', $companyId)
            ->where('company_name', $data['customer_name'])
            ->first();

        if ($customer) {
            return $customer;
        }

        // Create new customer if not found
        try {
            $email = null;
            $phoneNumber = null;
            if (filter_var($data['customer_contact'], FILTER_VALIDATE_EMAIL)) {
                $email = $data['customer_contact'];
            } else {
                $phoneNumber = $data['customer_contact'];
            }
            $customerService = app(CustomerService::class);
            $customer = Customer::create([
                'email' => $email,
                'company_name' => $data['customer_name'],
                'customerID' => $customerService->generateCompanyReference(),
                'customer_type' => 'individual',
                'business_type' => 'proprietorship',
                'currency_id' => $this->getCurrentCompanyCurrency()->id,
                'phone_number' => $phoneNumber,
                'company_id' => $companyId,
                'created_by' => $this->getCurrentUserId()
            ]);

            $this->addWarning("Row {$rowNumber}: Created new customer '{$data['customer_name']}'");
            return $customer;
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: Failed to create customer '{$data['customer_name']}': " . $e->getMessage());
            return null;
        }
    }


    /**
     * Find currency
     */
    protected function findCurrency(string $currency): ?Currency
    {
        return Currency::where('code', $currency)->first();
    }
}
