<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\LineItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class InvoiceBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'invoice_number' => 'required|string|max:100',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'status' => 'nullable|in:draft,sent,paid,overdue,cancelled',
            'currency' => 'nullable|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string|max:1000',
            'item_description' => 'nullable|string|max:500',
            'item_quantity' => 'nullable|numeric|min:0.01',
            'item_rate' => 'nullable|numeric|min:0',
            'item_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Invoice Number',
            'Customer Name',
            'Customer Email',
            'Issue Date',
            'Due Date',
            'Status',
            'Currency',
            'Tax Rate (%)',
            'Discount Amount',
            'Discount Type',
            'Notes',
            'Item Description',
            'Item Quantity',
            'Item Rate',
            'Item Amount',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'INV-001',
                'John Doe',
                'john.doe@example.com',
                '2024-01-15',
                '2024-02-15',
                'draft',
                'USD',
                '8.5',
                '50.00',
                'fixed',
                'Thank you for your business',
                'Consulting Services',
                '10',
                '100.00',
                '1000.00',
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
            // Find or create customer
            $customer = $this->findOrCreateCustomer($validatedData, $rowNumber);

            if (!$customer) {
                return null;
            }

            // Calculate totals
            $itemAmount = $validatedData['item_amount'] ??
                (($validatedData['item_quantity'] ?? 1) * ($validatedData['item_rate'] ?? 0));

            $subtotal = $itemAmount;
            $discountAmount = $this->calculateDiscount($subtotal, $validatedData);
            $discountedSubtotal = $subtotal - $discountAmount;
            $taxAmount = $this->calculateTax($discountedSubtotal, $validatedData);
            $total = $discountedSubtotal + $taxAmount;

            // Check for duplicate invoice number
            $existingInvoice = Invoice::where('company_id', $this->getCurrentCompanyId())
                ->where('invoice_number', $validatedData['invoice_number'])
                ->first();

            if ($existingInvoice) {
                $this->addError("Row {$rowNumber}: Invoice number '{$validatedData['invoice_number']}' already exists");
                return null;
            }

            // Prepare invoice data
            $invoiceData = [
                'invoice_number' => $validatedData['invoice_number'],
                'customer_id' => $customer->id,
                'issue_date' => Carbon::parse($validatedData['issue_date'])->format('Y-m-d'),
                'due_date' => Carbon::parse($validatedData['due_date'])->format('Y-m-d'),
                'status' => $validatedData['status'] ?? 'draft',
                'currency' => $validatedData['currency'] ?? 'USD',
                'tax_rate' => $validatedData['tax_rate'] ?? 0,
                'discount_amount' => $discountAmount,
                'discount_type' => $validatedData['discount_type'] ?? 'fixed',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'notes' => $validatedData['notes'],
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];

            // Prepare line item data
            $lineItemData = [
                'description' => $validatedData['item_description'] ?? 'Default Item',
                'quantity' => $validatedData['item_quantity'] ?? 1,
                'rate' => $validatedData['item_rate'] ?? 0,
                'amount' => $itemAmount,
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];

            return [
                'invoice' => $invoiceData,
                'line_items' => [$lineItemData],
            ];
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return Invoice::class;
    }

    public function getModuleName(): string
    {
        return 'Sales Invoice';
    }

    public function getValidationMessages(): array
    {
        return array_merge(parent::getValidationMessages(), [
            'invoice_number.required' => 'Invoice number is required.',
            'customer_name.required' => 'Customer name is required.',
            'issue_date.required' => 'Issue date is required.',
            'due_date.required' => 'Due date is required.',
            'due_date.after_or_equal' => 'Due date must be on or after issue date.',
            'tax_rate.numeric' => 'Tax rate must be a number.',
            'tax_rate.min' => 'Tax rate cannot be negative.',
            'tax_rate.max' => 'Tax rate cannot exceed 100%.',
        ]);
    }

    public function getMaxRows(): int
    {
        return 200; // Invoices are complex, so lower limit
    }

    public function shouldProcessAsync(): bool
    {
        return true; // Always process invoices asynchronously
    }

    /**
     * Find or create customer
     */
    protected function findOrCreateCustomer(array $data, int $rowNumber): ?Customer
    {
        $companyId = $this->getCurrentCompanyId();

        // Try to find by email first
        if (!empty($data['customer_email'])) {
            $customer = Customer::where('company_id', $companyId)
                ->where('email', $data['customer_email'])
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        // Try to find by name
        $customer = Customer::where('company_id', $companyId)
            ->where('name', $data['customer_name'])
            ->first();

        if ($customer) {
            return $customer;
        }

        // Create new customer if not found
        try {
            $customer = Customer::create([
                'name' => $data['customer_name'],
                'email' => $data['customer_email'],
                'company_id' => $companyId,
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ]);

            $this->addWarning("Row {$rowNumber}: Created new customer '{$data['customer_name']}'");
            return $customer;
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: Failed to create customer '{$data['customer_name']}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate discount amount
     */
    protected function calculateDiscount(float $subtotal, array $data): float
    {
        $discountAmount = $data['discount_amount'] ?? 0;
        $discountType = $data['discount_type'] ?? 'fixed';

        if ($discountType === 'percentage') {
            return ($subtotal * $discountAmount) / 100;
        }

        return min($discountAmount, $subtotal);
    }

    /**
     * Calculate tax amount
     */
    protected function calculateTax(float $subtotal, array $data): float
    {
        $taxRate = $data['tax_rate'] ?? 0;
        return ($subtotal * $taxRate) / 100;
    }
}
