<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Quote;
use App\Models\Customer;
use App\Services\Quotes\QuoteService;

class QuoteBulkUpload extends BulkUploadAbstract
{
    protected QuoteService $quoteService;
    public function __construct()
    {
        $this->quoteService = app(QuoteService::class);
    }

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
                'QUO-045',
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

            // Check for duplicate quote number
            $existingQuote = Quote::where('company_id', $this->getCurrentCompanyId())
                ->where('quoteID', $validatedData['quote_number'])
                ->first();

            // Calculate totals properly
            $unitPrice = $validatedData['item_unit_price'];
            $quantity = $validatedData['item_quantity'] ?? 1;
            $discountPercent = $validatedData['item_discount'] ?? 0;
            $vatPercent = $validatedData['item_vat'] ?? 0;

            // Calculate line item total using QuoteService methods
            $totalUnitPrice = $this->quoteService->calculateLineItemTotalUnitPrice($unitPrice, $quantity);
            $lineItemTotal = $this->quoteService->calculateLineItemTotal($totalUnitPrice, $discountPercent, $vatPercent);

            $subtotal = $lineItemTotal;
            $additionalCharge = $validatedData['additional_charge'] ?? 0;
            $total = $this->quoteService->calculateQuoteTotal($subtotal, 0, $additionalCharge);


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

            // Prepare quote data
            $quoteData = [
                'id' => $existingQuote->id ?? null, // Pass existing ID for updates
                'quote_date' => \Carbon\Carbon::parse($validatedData['issue_date'])->format('Y-m-d H:i:s'),
                'additional_referenceID' => $this->quoteService->generateQuoteId(),
                'quoteID' => $validatedData['quote_number'],
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'customer_note' => $validatedData['customer_note'],
                'sub_total' => $subtotal,
                'shipping_charge' => 0, // Default to 0 if not provided
                'additional_charge' => $additionalCharge,
                'quote_total' => $total,
                'save_status' => $validatedData['status'] ?? 'draft',
                'share_status' => 'not_shared', // Default share status
                'category_id' => $category->id ?? null,
                'currency_id' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId(),
                'customer_id' => $customer->id
            ];
            $lineItemData = [
                'item_details' => $validatedData['item_description'] ?? 'Default Item',
                'quantity' => $quantity,
                'price' => $unitPrice,
                'discount' => $discountPercent, // Store percentage, not amount
                'vat' => $vatPercent, // Store percentage, not amount
                'amount' => $lineItemTotal,
                'category_id' => $itemCategory->id ?? null,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId()
            ];

            return [
                'quote' => $quoteData,
                'line_items' => [$lineItemData], // Single line item per row
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
            // Prepare data for QuoteService updateOrCreate method
            $quoteData = $data['quote'];
            $lineItems = $data['line_items'];

            // Add line items to quote data for QuoteService to handle
            $quoteData['line_items'] = $lineItems;

            // Use QuoteService as single source of truth for quote creation
            $quote = $this->quoteService->updateOrCreate($quoteData);
            return $quote;
        } catch (\Exception $e) {
            $this->addError("Failed to create quote: " . $e->getMessage());
            return null;
        }
    }

    // /**
    //  * Perform post-creation operations for quotes
    //  * This includes adding line items and other operations
    //  * 
    //  * @param \App\Models\Quote $quote
    //  * @param array $data
    //  * @return void
    //  */
    // protected function performPostCreationOperations($quote, array $data): void
    // {
    //     try {
    //         // Add line items to the quote
    //         if (isset($data['line_items']) && is_array($data['line_items'])) {
    //             $quote->addLineItems($data['line_items']);
    //         }
    //     } catch (\Exception $e) {
    //         $this->addError("Failed to perform post-creation operations for quote {$quote->quoteID}: " . $e->getMessage());
    //     }
    // }
}
