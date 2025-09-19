<?php

namespace App\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RequestValidationService
{
    /**
     * Extract validation rules from a request class
     */
    public static function extractRules(string $requestClass): array
    {
        if (!class_exists($requestClass)) {
            return [];
        }

        $request = new $requestClass();

        if (!$request instanceof FormRequest) {
            return [];
        }

        return $request->rules();
    }

    /**
     * Convert validation rules to template headers
     */
    public static function rulesToHeaders(array $rules): array
    {
        $headers = [];

        foreach ($rules as $field => $rule) {
            // Skip nested array fields for now (like contact_persons.*.field)
            if (str_contains($field, '.*.')) {
                continue;
            }

            // Convert field name to readable header
            $header = self::fieldToHeader($field);
            $headers[] = $header;
        }

        return $headers;
    }

    /**
     * Convert validation rules to sample data
     */
    public static function rulesToSampleData(array $rules): array
    {
        $sampleData = [];

        foreach ($rules as $field => $rule) {
            // Skip nested array fields for now
            if (str_contains($field, '.*.')) {
                continue;
            }

            $sampleValue = self::generateSampleValue($field, $rule);
            $sampleData[] = $sampleValue;
        }

        return [$sampleData];
    }

    /**
     * Convert field name to readable header
     */
    protected static function fieldToHeader(string $field): string
    {
        // Remove _id suffix for better readability
        $field = str_replace('_id', '', $field);

        // Convert snake_case to Title Case
        return Str::title(str_replace('_', ' ', $field));
    }

    /**
     * Generate sample value based on field name and validation rules
     */
    protected static function generateSampleValue(string $field, $rules): string
    {
        $fieldLower = strtolower($field);
        $rulesArray = is_string($rules) ? explode('|', $rules) : (is_array($rules) ? $rules : []);

        // Handle specific field types
        if (str_contains($fieldLower, 'email')) {
            return 'john.doe@example.com';
        }

        if (str_contains($fieldLower, 'phone')) {
            return '+1-555-0123';
        }

        if (str_contains($fieldLower, 'date')) {
            return '2024-01-15';
        }

        if (str_contains($fieldLower, 'name') && str_contains($fieldLower, 'full')) {
            return 'John Doe';
        }

        if (str_contains($fieldLower, 'name') && str_contains($fieldLower, 'display')) {
            return 'John Doe';
        }

        if (str_contains($fieldLower, 'company_name') || str_contains($fieldLower, 'vendor_name')) {
            return 'ABC Company Ltd';
        }

        if (str_contains($fieldLower, 'address')) {
            return '123 Main Street';
        }

        if (str_contains($fieldLower, 'city')) {
            return 'New York';
        }

        if (str_contains($fieldLower, 'state')) {
            return 'NY';
        }

        if (str_contains($fieldLower, 'zip_code') || str_contains($fieldLower, 'post_code')) {
            return '10001';
        }

        if (str_contains($fieldLower, 'currency')) {
            return 'USD';
        }

        if (str_contains($fieldLower, 'business_type')) {
            return 'corporation';
        }

        if (str_contains($fieldLower, 'customer_type')) {
            return 'individual';
        }

        if (str_contains($fieldLower, 'industry')) {
            return 'Technology';
        }

        if (str_contains($fieldLower, 'employee_count')) {
            return '50';
        }

        if (str_contains($fieldLower, 'vat_number') || str_contains($fieldLower, 'tax_number')) {
            return 'TAX123456789';
        }

        if (str_contains($fieldLower, 'business_registration_number')) {
            return 'REG123456789';
        }

        if (str_contains($fieldLower, 'invoice_number') || str_contains($fieldLower, 'quote_number')) {
            return 'INV-001';
        }

        if (str_contains($fieldLower, 'due_date') || str_contains($fieldLower, 'expiry_date')) {
            return '2024-02-15';
        }

        if (str_contains($fieldLower, 'start_date') || str_contains($fieldLower, 'issue_date')) {
            return '2024-01-15';
        }

        if (str_contains($fieldLower, 'amount') || str_contains($fieldLower, 'price') || str_contains($fieldLower, 'total')) {
            return '1000.00';
        }

        if (str_contains($fieldLower, 'quantity')) {
            return '10';
        }

        if (str_contains($fieldLower, 'discount')) {
            return '50.00';
        }

        if (str_contains($fieldLower, 'vat') || str_contains($fieldLower, 'tax_rate')) {
            return '8.5';
        }

        if (str_contains($fieldLower, 'notes') || str_contains($fieldLower, 'description')) {
            return 'Sample description or notes';
        }

        if (str_contains($fieldLower, 'terms_and_conditions')) {
            return 'Terms and conditions text';
        }

        if (str_contains($fieldLower, 'customer_note')) {
            return 'Customer note text';
        }

        if (str_contains($fieldLower, 'shipping_charge')) {
            return '25.00';
        }

        if (str_contains($fieldLower, 'additional_charge')) {
            return '10.00';
        }

        if (str_contains($fieldLower, 'sub_total')) {
            return '1000.00';
        }

        if (str_contains($fieldLower, 'invoice_value')) {
            return '1035.00';
        }

        if (str_contains($fieldLower, 'item_details')) {
            return 'Product or Service Name';
        }

        if (str_contains($fieldLower, 'total_unit_price')) {
            return '1000.00';
        }

        if (str_contains($fieldLower, 'line_items')) {
            return '1'; // This will be handled differently
        }

        // Handle ID fields
        if (str_ends_with($fieldLower, '_id')) {
            return '1';
        }

        // Handle boolean fields
        if (in_array('boolean', $rulesArray) || in_array('in:0,1', $rulesArray) || in_array('in:1,0', $rulesArray)) {
            return '1';
        }

        // Handle required fields
        if (in_array('required', $rulesArray)) {
            if (in_array('string', $rulesArray)) {
                return 'Sample Text';
            }
            if (in_array('integer', $rulesArray)) {
                return '1';
            }
            if (in_array('numeric', $rulesArray)) {
                return '100.00';
            }
            if (in_array('email', $rulesArray)) {
                return 'example@email.com';
            }
            if (in_array('date', $rulesArray) || in_array('date_format:Y-m-d', $rulesArray)) {
                return '2024-01-15';
            }
        }

        // Default for nullable fields
        return '';
    }

    /**
     * Get field type from validation rules
     */
    public static function getFieldType(array $rules): string
    {
        $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

        if (in_array('email', $rulesArray)) {
            return 'email';
        }

        if (in_array('numeric', $rulesArray)) {
            return 'numeric';
        }

        if (in_array('integer', $rulesArray)) {
            return 'integer';
        }

        if (in_array('date', $rulesArray) || in_array('date_format:Y-m-d', $rulesArray)) {
            return 'date';
        }

        if (in_array('boolean', $rulesArray)) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * Check if field is required
     */
    public static function isFieldRequired($rules): bool
    {
        $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;
        return in_array('required', $rulesArray);
    }

    /**
     * Get validation messages for field
     */
    public static function getFieldValidationMessages(string $field, $rules): array
    {
        $messages = [];
        $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;

        if (self::isFieldRequired($rules)) {
            $messages[] = 'Required field';
        }

        if (in_array('email', $rulesArray)) {
            $messages[] = 'Must be a valid email address';
        }

        if (in_array('numeric', $rulesArray)) {
            $messages[] = 'Must be a number';
        }

        if (in_array('integer', $rulesArray)) {
            $messages[] = 'Must be a whole number';
        }

        if (in_array('date', $rulesArray) || in_array('date_format:Y-m-d', $rulesArray)) {
            $messages[] = 'Must be a valid date (YYYY-MM-DD)';
        }

        if (in_array('boolean', $rulesArray)) {
            $messages[] = 'Must be 0 or 1';
        }

        // Extract min/max values
        foreach ($rulesArray as $rule) {
            if (str_starts_with($rule, 'min:')) {
                $messages[] = 'Minimum value: ' . substr($rule, 4);
            }
            if (str_starts_with($rule, 'max:')) {
                $messages[] = 'Maximum value: ' . substr($rule, 4);
            }
        }

        return $messages;
    }
}
