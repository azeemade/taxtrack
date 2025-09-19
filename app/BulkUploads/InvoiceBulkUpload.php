<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\LineItem;
use App\Models\Quote;
use App\Services\Customer\CustomerService;
use App\Services\Invoices\InvoiceService;
use Carbon\Carbon;
use Nnjeim\World\Models\Currency;

class InvoiceBulkUpload extends BulkUploadAbstract
{

    public function getValidationRules(): array
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_contact' => 'required|string|max:255',
            'currency_code' => 'required|string|size:3',
            'category' => 'nullable|string',
            'invoice_number' => 'required|string|max:100',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'terms_and_conditions' => 'nullable|string|max:1000',
            'customer_notes' => 'nullable|string|max:1000',
            'quote_number' => 'nullable|string|max:100|exists:quotes,quoteID,company_id,' . $this->getCurrentCompanyId(),
            'status' => 'nullable|in:draft,sent,paid,overdue,cancelled',
            'payment_status' => 'nullable|in:pending,partial-payment,full-payment',
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
            'Customer Name',
            'Customer Contact',
            'Currency Code',
            'Category',
            'Invoice Number',
            'Issue Date',
            'Due Date',
            'Terms and Conditions',
            'Customer Notes',
            'Quote Number',
            'Status',
            'Payment Status',
            'Item Description',
            'Item Category',
            'Item Quantity',
            'Item Discount',
            'Item VAT',
            'Item Unit Price'
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'John Doe',
                'john.doe@example.com',
                'USD',
                'Services',
                'INV-001',
                '2024-01-15',
                '2024-02-15',
                'Thank you for your business',
                'Consulting Services',
                '',
                'draft',
                'pending',
                'Consulting Services',
                'Services',
                '10',
                '50.00',
                '8.5',
                '100.00'
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
            $subtotal = $validatedData['item_unit_price'] * ($validatedData['item_quantity'] ?? 1);

            $discountAmount = $this->calculateDiscount($subtotal, $validatedData);
            $discountedSubtotal = $subtotal - $discountAmount;
            $taxAmount = $this->calculateTax($discountedSubtotal, $validatedData);
            $total = $discountedSubtotal + $taxAmount;

            // Check for duplicate invoice number
            $existingInvoice = Invoice::where('invoiceID', $validatedData['invoice_number'])
                ->first();

            if ($existingInvoice) {
                $this->addWarning("Row {$rowNumber}: Invoice number '{$validatedData['invoice_number']}' already exists. The value will be updated");
            }

            $quote = null;
            if ($validatedData['quote_number']) {
                $quote = Quote::where('quoteID', $validatedData['quote_number'])
                    ->where('company_id', $this->getCurrentCompanyId())
                    ->first();
            }

            $currency = $this->findCurrency($validatedData['currency_code']);

            $category = null;
            if ($validatedData['category']) {
                $category = $this->findCategory($validatedData['category'], 'invoices');
            }

            $itemCategory = null;
            if ($validatedData['item_category']) {
                $itemCategory = $this->findCategory($validatedData['item_category'], 'line_items');
            }

            $invoiceService = app(InvoiceService::class);


            // Prepare invoice data
            $invoiceData = [
                'invoiceID' => $validatedData['invoice_number'],
                'referenceID' => $invoiceService->generateRefId(),
                'additional_referenceID' => $invoiceService->generateRefId(),
                'start_date' => Carbon::parse($validatedData['issue_date'])->format('Y-m-d'),
                'due_date' => Carbon::parse($validatedData['due_date'])->format('Y-m-d'),
                'terms_and_conditions' => $validatedData['terms_and_conditions'],
                'customer_note' => $validatedData['customer_notes'],
                'status' => $validatedData['status'] ?? 'draft',
                'sub_total' => $subtotal,
                'additional_charge' => $validatedData['additional_charge'] ?? 0,
                'invoice_value' => $total,
                'payment_status' => $validatedData['payment_status'] ?? 'pending',
                'customer_id' => $customer->id,
                'currency_id' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'quote_id' => $quote->id ?? null,
                'category_id' => $category->id ?? null,
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId()
            ];

            // Prepare line item data
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
     * Create complex invoice record with line items
     * 
     * @param array $data
     * @return \App\Models\Invoice|null
     */
    protected function createComplexRecord(array $data)
    {
        if (!isset($data['invoice']) || !isset($data['line_items'])) {
            return null;
        }

        try {
            // Create the invoice first
            $invoice = Invoice::create($data['invoice']);

            return $invoice;
        } catch (\Exception $e) {
            $this->addError("Failed to create invoice: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Perform post-creation operations for invoices
     * This includes adding line items and other operations similar to InvoiceService::updateOrCreate
     * 
     * @param \App\Models\Invoice $invoice
     * @param array $data
     * @return void
     */
    protected function performPostCreationOperations($invoice, array $data): void
    {
        try {
            // Add line items to the invoice
            if (isset($data['line_items']) && is_array($data['line_items'])) {
                $invoice->addLineItems($data['line_items']);
            }

            // Update quote status if this invoice was created from a quote
            if (isset($data['invoice']['quote_id']) && $data['invoice']['quote_id']) {
                $quote = Quote::find($data['invoice']['quote_id']);
                if ($quote) {
                    $quote->update([
                        'status' => \App\Enums\FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->addError("Failed to perform post-creation operations for invoice {$invoice->invoiceID}: " . $e->getMessage());
        }
    }
}
