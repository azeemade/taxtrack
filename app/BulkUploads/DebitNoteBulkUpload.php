<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Enums\DocumentableModelEnums;
use App\Enums\ShareStatusEnums;
use App\Models\DebitNote;
use App\Models\LineItem;
use App\Models\PurchaseInvoice;
use App\Services\DebitNotes\DebitNoteService;

class DebitNoteBulkUpload extends BulkUploadAbstract
{
    protected DebitNoteService $debitNoteService;

    public function __construct()
    {
        $this->debitNoteService = app(DebitNoteService::class);
    }

    public function getValidationRules(): array
    {
        return [
            'debit_note_number' => 'required|string|max:100',
            'status' => 'required|string|in:draft,issued',
            'date_issued' => 'required|date',
            'supplier_name' => 'required|string|max:255',
            'supplier_contact' => 'required|string|max:255',
            'line_item_invoice_number' => 'required|exists:purchase_invoices,purchase_invoiceID,company_id,' . $this->getCurrentCompanyId(),
            'line_item_id' => ['required', 'exists:line_items,id,company_id,' . $this->getCurrentCompanyId()], //, $this->validateLineItem()],
            'amount' => ['required', $this->validateDebitAmount()]
        ];
    }

    protected function validateDebitAmount(): \Closure
    {
        return function ($attribute, $value, $fail, $validator) {
            $data = $validator->getData();
            if (!isset($data['line_item_invoice_number'])) {
                $fail('Invoice number is missing for credit amount validation');
                return;
            }

            $invoice = PurchaseInvoice::where('purchase_invoiceID', $data['line_item_invoice_number'])
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$invoice) {
                $fail("Invoice with number {$data['line_item_invoice_number']} not found");
                return;
            }

            $lineItem = LineItem::where('id', $data['line_item_id'])
                ->where('documentable_id', $invoice->id)
                ->where('documentable_type', DocumentableModelEnums::PURCHASE_INVOICE->value)
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            if (!$lineItem) {
                $fail("Line item with id {$data['line_item_id']} not found for invoice {$data['line_item_invoice_number']}");
                return;
            }

            if ($lineItem && ($value + $lineItem->debit_amount) > $lineItem->amount) {
                $fail("Credit amount ({$value}) cannot be greater than line item amount ({$lineItem->amount})");
            }
        };
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Debit Note Number',
            'Status',
            'Date Issued',
            'Supplier Name',
            'Supplier Contact',
            'Line Item Invoice Number',
            'Line Item ID',
            'Amount'
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'DN-001',
                'draft',
                '2024-01-15',
                'ABC Supplies Ltd',
                'contact@abcsupplies.com',
                'PID-0002',
                '317641',
                '150.00'
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        $validatedData = $this->validateRow($row, $rowNumber);

        if ($validatedData === false) {
            return null;
        }

        try {
            // Find or create supplier (reuse logic from InvoiceBulkUpload)
            $supplier = $this->findOrCreateSupplier($validatedData, $rowNumber);

            if (!$supplier) {
                return null;
            }

            #existingDebitNote
            $existingDebitNote = DebitNote::where('company_id', $this->getCurrentCompanyId())
                ->where('noteID', $validatedData['debit_note_number'])
                ->where('vendor_id', $supplier->id)
                ->first();

            if ($existingDebitNote) {
                $this->addWarning("Row {$rowNumber}: Debit note number '{$validatedData['debit_note_number']}' already exists. The value will be updated");
            }

            $invoice = PurchaseInvoice::where('purchase_invoiceID', $validatedData['line_item_invoice_number'])
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            $lineItem = LineItem::where('id', $validatedData['line_item_id'])
                ->where('documentable_id', $invoice->id)
                ->where('documentable_type', DocumentableModelEnums::PURCHASE_INVOICE->value)
                ->where('company_id', $this->getCurrentCompanyId())
                ->first();

            return [
                'noteID' => $validatedData['debit_note_number'],
                'save_status' => $validatedData['status'],
                'share_status' => ShareStatusEnums::NOT_SHARED->value,
                'vendor_id' => $supplier->id,
                'date_issued' => $validatedData['date_issued'],
                'created_by' => $this->getCurrentUserId(),
                'company_id' => $this->getCurrentCompanyId(),
                'id' => $existingDebitNote->id ?? null,
                'items' => [
                    [
                        'model' => 'purchase_invoices',
                        'model_id' => $invoice->id,
                        'line_item_id' => $lineItem->id,
                        'debit_amount' => $validatedData['amount'],
                        'debit_in_full' => $validatedData['amount'] == $lineItem->amount,
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
        return DebitNote::class;
    }

    public function getModuleName(): string
    {
        return 'Debit Note';
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
     * Create complex debit note record with line items
     * 
     * @param array $data
     * @return \App\Models\DebitNote|null
     */
    protected function createComplexRecord(array $data)
    {
        if (!isset($data) || !isset($data['items'])) {
            $this->addError("Invalid debit note data structure: missing items array");
            return null;
        }

        try {
            if (isset($data['id'])) {
                $debitNote = $this->debitNoteService->update($data);
            } else {
                $debitNote = $this->debitNoteService->create($data);
            }
            return $debitNote;
        } catch (\Exception $e) {
            $this->addError("Failed to create debit note: " . $e->getMessage());
            return null;
        }
    }
}
