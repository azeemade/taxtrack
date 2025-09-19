<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Quote;
use App\Models\Customer;
use App\Services\Quotes\QuoteService;

class QuoteBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'issue_date' => 'required|date',
            'quote_number' => 'required|string|max:100',
            'terms_and_conditions' => 'nullable|string|max:1000',
            'customer_note' => 'nullable|string|max:1000',
            'customer_name' => 'required|string|max:255',
            'customer_contact' => 'required|string|max:255',
            'status' => 'nullable|in:draft,sent,accepted,declined,expired',
            'category' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'additional_charge' => 'nullable|numeric|min:0',
            'item_description' => 'required|string|max:500',
            'item_category' => 'nullable|string|max:500',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_discount' => 'nullable|numeric|min:0|max:100',
            'item_vat' => 'nullable|numeric|min:0|max:100',
            'item_unit_price' => 'required|numeric|min:0'
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Issue Date',
            'Quote Number',
            'Terms and Conditions',
            'Customer Note',
            'Customer Name',
            'Customer Contact',
            'Status',
            'Category',
            'Currency',
            'Additional Charge',
            'Item Description',
            'Item Category',
            'Item Quantity',
            'Item Discount',
            'Item VAT',
            'Item Unit Price',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                '2024-01-15',
                'QUO-001',
                'Thank you for your business',
                'Consulting Services',
                'Jon Doe',
                'john.doe@example.com',
                'draft',
                'Consulting Services',
                'NGN',
                '10.00',
                'Item 1',
                'Consulting Services',
                '10',
                '50.00',
                '8.5',
                '100.00',
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
            // Find or create customer (reuse logic from InvoiceBulkUpload)
            $customer = $this->findOrCreateCustomer($validatedData, $rowNumber);

            if (!$customer) {
                return null;
            }

            // Calculate totals (similar to InvoiceBulkUpload)
            $subtotal = $validatedData['item_unit_price'] * ($validatedData['item_quantity'] ?? 1);

            $discountAmount = $this->calculateDiscount($subtotal, $validatedData);
            $discountedSubtotal = $subtotal - $discountAmount;
            $taxAmount = $this->calculateTax($discountedSubtotal, $validatedData);
            $total = $discountedSubtotal + $taxAmount;

            // Check for duplicate quote number
            $existingQuote = Quote::where('company_id', $this->getCurrentCompanyId())
                ->where('quoteID', $validatedData['quote_number'])
                ->first();

            if ($existingQuote) {
                $this->addWarning("Row {$rowNumber}: Quote number '{$validatedData['quote_number']}' already exists. The value will be updated");
            }

            $currency = $this->findCurrency($validatedData['currency']);

            if ($validatedData['category']) {
                $category = $this->findCategory($validatedData['category'], 'invoices');
            }
            if ($validatedData['item_category']) {
                $itemCategory = $this->findCategory($validatedData['item_category'], 'line_items');
            }

            $quoteService = app(QuoteService::class);

            // Prepare quote data
            $quoteData = [
                'quote_date' => \Carbon\Carbon::parse($validatedData['issue_date'])->format('Y-m-d H:i:s'),
                'additional_referenceID' => $quoteService->generateQuoteId(),
                'quoteID' => $validatedData['quote_number'],
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'customer_note' => $validatedData['customer_note'],
                'sub_total' => $subtotal,
                'additional_charge' => $validatedData['additional_charge'] ?? 0,
                'quote_total' => $total,
                'status' => $validatedData['status'] ?? 'draft',
                'category_id' => $category->id ?? null,
                'currency_id' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId(),
                'customer_id' => $customer->id
            ];
            $lineItemData = [
                'item_details' => $validatedData['item_description'] ?? 'Default Item',
                'quantity' => $validatedData['item_quantity'] ?? 1,
                'price' => $validatedData['item_unit_price'] ?? 0,
                'discount' => $discountAmount,
                'vat' => $taxAmount,
                'amount' => $subtotal,
                'category_id' => $itemCategory->id ?? null,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId()
            ];

            return [
                'quote' => $quoteData,
                'line_items' => [$lineItemData],
            ];
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return Quote::class;
    }

    public function getModuleName(): string
    {
        return 'Sales Quote';
    }

    public function getValidationMessages(): array
    {
        return array_merge(parent::getValidationMessages(), [
            'quote_number.required' => 'Quote number is required.',
            'customer_name.required' => 'Customer name is required.',
            'issue_date.required' => 'Issue date is required.',
        ]);
    }

    public function getMaxRows(): int
    {
        return 200;
    }

    public function shouldProcessAsync(): bool
    {
        return true;
    }

    /**
     * Calculate discount amount
     */
    protected function calculateDiscount(float $subtotal, array $data): float
    {
        $discount = $data['item_discount'] ?? 0;
        return ($subtotal * $discount) / 100;
    }

    /**
     * Calculate tax amount
     */
    protected function calculateTax(float $subtotal, array $data): float
    {
        $taxRate = $data['item_vat'] ?? 0;
        return ($subtotal * $taxRate) / 100;
    }

    /**
     * Create complex quote record with line items
     * 
     * @param array $data
     * @return \App\Models\Quote|null
     */
    protected function createComplexRecord(array $data)
    {
        if (!isset($data['quote']) || !isset($data['line_items'])) {
            return null;
        }

        try {
            // Create the quote first
            $quote = Quote::create($data['quote']);

            return $quote;
        } catch (\Exception $e) {
            $this->addError("Failed to create quote: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Perform post-creation operations for quotes
     * This includes adding line items and other operations
     * 
     * @param \App\Models\Quote $quote
     * @param array $data
     * @return void
     */
    protected function performPostCreationOperations($quote, array $data): void
    {
        try {
            // Add line items to the quote
            if (isset($data['line_items']) && is_array($data['line_items'])) {
                $quote->addLineItems($data['line_items']);
            }
        } catch (\Exception $e) {
            $this->addError("Failed to perform post-creation operations for quote {$quote->quoteID}: " . $e->getMessage());
        }
    }
}
