<?php

namespace App\Contracts;

interface BulkUploadContract
{
    /**
     * Get the validation rules for bulk upload
     *
     * @return array
     */
    public function getValidationRules(): array;

    /**
     * Get the headers/columns for the bulk upload template
     *
     * @param string|null $subType Optional subtype for modules with multiple templates
     * @return array
     */
    public function getTemplateHeaders(?string $subType = null): array;

    /**
     * Get sample data for the template
     *
     * @param string|null $subType Optional subtype for modules with multiple templates
     * @return array
     */
    public function getTemplateSampleData(?string $subType = null): array;

    /**
     * Get available template subtypes for this module
     *
     * @return array
     */
    public function getAvailableTemplateTypes(): array;

    /**
     * Process a single row of data
     *
     * @param array $row
     * @param int $rowNumber
     * @return array|null Returns the processed data or null if validation fails
     */
    public function processRow(array $row, int $rowNumber): ?array;

    /**
     * Get the model class name for this bulk upload
     *
     * @return string
     */
    public function getModelClass(): string;

    /**
     * Get the module name for this bulk upload
     *
     * @return string
     */
    public function getModuleName(): string;

    /**
     * Get custom validation messages
     *
     * @return array
     */
    public function getValidationMessages(): array;

    /**
     * Get the maximum number of rows allowed per upload
     *
     * @return int
     */
    public function getMaxRows(): int;

    /**
     * Check if this upload should be processed asynchronously
     *
     * @return bool
     */
    public function shouldProcessAsync(): bool;

    /**
     * Get all errors
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Get all warnings
     *
     * @return array
     */
    public function getWarnings(): array;

    /**
     * Get processing statistics
     *
     * @return array
     */
    public function getStatistics(): array;

    /**
     * Increment processed rows
     */
    public function incrementProcessedRows(): void;

    /**
     * Increment successful rows
     */
    public function incrementSuccessfulRows(): void;

    /**
     * Increment failed rows
     */
    public function incrementFailedRows(): void;

    /**
     * Add an error message
     *
     * @param string $message
     */
    public function addError(string $message): void;
}
