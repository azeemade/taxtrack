<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Enums\DocumentableModelEnums;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\LineItem;
use App\Services\CreditNotes\CreditNoteService;

class CreditNoteBulkUpload extends BulkUploadAbstract
{
    protected CreditNoteService $creditNoteService;

    public function __construct()
    {
        $this->creditNoteService = app(CreditNoteService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'reference_number' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'customer_contact' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'issue_date' => 'required|date',
            'status' => 'required|string|in:draft,issued',
            'line_item_invoice_number' => 'required|string',
            'line_item_id' => ['required', 'numeric', $this->validateLineItem()],
            'amount' => ['required', 'numeric', 'min:0', $this->validateCreditAmount()]
        ];
    }

    protected function validateLineItem(): \Closure
    {
        return function ($attribute, $value, $fail, $validator) {
            $data = $validator->getData();
            if (!isset($data['line_item_invoice_number'])) {
                $fail('Invoice number is missing for line item validation');
                return;
            }

            $invoice = Invoice::where('invoiceID', $data['line_item_invoice_number'])
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$invoice) {
                $fail("Invoice with number {$data['line_item_invoice_number']} not found");
                return;
            }

            $lineItem = LineItem::where('id', $value)
                ->where('documentable_id', $invoice->id)
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->where('company_id', $this->getCurrentCompanyId())
                ->exists();

            if (!$lineItem) {
                $fail("Line item with id {$value} is invalid or doesn't belong to invoice {$data['line_item_invoice_number']}");
                return;
            }
        };
    }

    protected function validateCreditAmount(): \Closure
    {
        return function ($attribute, $value, $fail, $validator) {
            $data = $validator->getData();
            if (!isset($data['line_item_invoice_number'])) {
                $fail('Invoice number is missing for credit amount validation');
                return;
            }

            $invoice = Invoice::where('invoiceID', $data['line_item_invoice_number'])
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$invoice) {
                $fail("Invoice with number {$data['line_item_invoice_number']} not found");
                return;
            }

            $lineItem = LineItem::where('id', $data['line_item_id'])
                ->where('documentable_id', $invoice->id)
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if ($lineItem && $value > $lineItem->amount) {
                $fail("Credit amount ({$value}) cannot be greater than line item amount ({$lineItem->amount})");
            }
        };
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Reference Number',
            'Customer Name',
            'Customer Contact',
            'Currency',
            'Issue Date',
            'Status',
            'Line Item Invoice Number',
            'Line Item Id',
            'Amount'
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'CN-1092',
                'John Doe',
                'john365@gmail.com',
                'NGN',
                '2024-01-15',
                'draft',
                'Inv-105868',
                '317602',
                '200'
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        // Convert heading row format to expected format
        $convertedRow = $this->convertHeadingRowToExpectedFormat($row);

        $validatedData = $this->validateRow($convertedRow, $rowNumber);

        if ($validatedData === false) {
            return null;
        }

        try {
            // Find or create customer
            $customer = $this->findOrCreateCustomer($validatedData, $rowNumber);

            if (!$customer) {
                return null;
            }

            $currency = $this->findCurrency($validatedData['currency']);

            // Find invoice with company scope
            $invoice = Invoice::where('invoiceID', $validatedData['line_item_invoice_number'])
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$invoice) {
                $this->addError("Row {$rowNumber}: Invoice with number '{$validatedData['line_item_invoice_number']}' not found");
                return null;
            }

            // Find line item
            $lineItem = LineItem::where('id', $validatedData['line_item_id'])
                ->where('documentable_id', $invoice->id)
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$lineItem) {
                $this->addError("Row {$rowNumber}: Line item with id '{$validatedData['line_item_id']}' not found for invoice '{$validatedData['line_item_invoice_number']}'");
                return null;
            }

            return [
                'referenceID' => $validatedData['reference_number'],
                'issue_date' => $validatedData['issue_date'],
                'save_status' => $validatedData['status'],
                'customer_id' => $customer->id,
                'currency_id' => $currency->id ?? $this->getCurrentCompanyCurrency()->id,
                'created_by' => $this->getCurrentUserId(),
                'invoice_id' => $invoice->id,
                'company_id' => $this->getCurrentCompanyId(),
                'invoices' => [
                    [
                        'invoice_id' => $invoice->id,
                        'line_item_id' => $validatedData['line_item_id'],
                        'credit_amount' => $validatedData['amount'],
                        'credit_in_full' => $validatedData['amount'] == $lineItem->amount,
                        'status' => 'added',
                        'created_by' => $this->getCurrentUserId(),
                        'company_id' => $this->getCurrentCompanyId(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return CreditNote::class;
    }

    public function getModuleName(): string
    {
        return 'Credit Note';
    }

    public function getMaxRows(): int
    {
        return 300;
    }

    public function shouldProcessAsync(): bool
    {
        return true;
    }

    /**
     * Convert heading row format to expected format
     * 
     * @param array $row
     * @return array
     */
    protected function convertHeadingRowToExpectedFormat(array $row): array
    {
        // Map the heading row keys to the expected format
        $mapping = [
            'reference_number' => 'reference_number',
            'customer_name' => 'customer_name',
            'customer_contact' => 'customer_contact',
            'currency' => 'currency',
            'issue_date' => 'issue_date',
            'status' => 'status',
            'line_item_invoice_number' => 'line_item_invoice_number',
            'line_item_id' => 'line_item_id',
            'amount' => 'amount'
        ];

        $convertedRow = [];
        foreach ($mapping as $expectedKey => $headingKey) {
            $convertedRow[$expectedKey] = $row[$headingKey] ?? null;
        }

        return $convertedRow;
    }

    /**
     * Create complex credit note record with line items
     * 
     * @param array $creditNoteData
     * @return \App\Models\CreditNote|null
     */
    protected function createComplexRecord(array $creditNoteData)
    {
        if (!isset($creditNoteData) || !isset($creditNoteData['invoices'])) {
            $this->addError("Invalid credit note data structure: missing invoices array");
            return null;
        }

        try {
            // Use CreditNoteService as single source of truth for credit note creation
            $creditNote = $this->creditNoteService->create($creditNoteData);
            return $creditNote;
        } catch (\Exception $e) {
            $this->addError("Failed to create credit note: " . $e->getMessage());
            return null;
        }
    }
}
