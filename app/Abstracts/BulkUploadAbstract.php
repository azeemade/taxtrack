<?php

namespace App\Abstracts;

use App\Contracts\BulkUploadContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class BulkUploadAbstract implements BulkUploadContract
{
    protected array $errors = [];
    protected array $warnings = [];
    protected int $processedRows = 0;
    protected int $successfulRows = 0;
    protected int $failedRows = 0;

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
     * Get current user ID
     *
     * @return int|null
     */
    protected function getCurrentUserId(): ?int
    {
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
        if (Auth::check()) {
            $user = Auth::user();
            return $user->current_company_id ?? null;
        }
        return null;
    }
}
