<?php

namespace App\Http\Requests\Company\Purchase\DebitNote;

use App\Enums\DocumentableModelEnums;
use App\Models\LineItem;
use Illuminate\Foundation\Http\FormRequest;

class CreateDebitNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'vendor_id' => 'required|integer|exists:vendors,id',
            'date_issued' => 'required|date_format:Y-m-d',
            'attachments' => 'nullable|string',
            'additional_referenceID' => 'nullable|string|max:20',
            'save_status' => 'required|in:save,draft,send',
            'items' => 'required|array|min:1',
            'items.*.model' => 'required|string|in:purchase_invoices,vendor_bills',
            'items.*.model_id' => 'required|integer',
            'items.*.line_item_id' => [
                'required',
                'integer',
                'exists:line_items,id',
                $this->validateLineItem(),
            ],
            'items.*.debit_amount' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateDebitAmount(),
            ],
            'items.*.debit_in_full' => [
                'required',
                'boolean',
                $this->validateDebitInFull(),
            ],
        ];

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['items.*.status'] = 'required|string|in:added,removed';
        }
        return $rules;
    }

    /**
     * Validate the line item.
     */
    private function validateLineItem(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("items.$index");

            if (!isset($item['model_id'])) {
                $fail('Model id is missing for item ' . ($index + 1));
                return;
            }

            $documentableType = $item['model'] === 'purchase_invoices' ? DocumentableModelEnums::PURCHASE_INVOICE->value : DocumentableModelEnums::VENDOR_BILLS->value;

            $lineItem = LineItem::where('id', $value)
                ->where('documentable_id', $item['model_id'])
                ->where('documentable_type', $documentableType)
                ->exists();

            if (!$lineItem) {
                $fail('Line item ' . ($index + 1) . ' is invalid');
            }
        };
    }

    /**
     * Validate the debit amount.
     */
    private function validateDebitAmount(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("items.$index");


            if (!isset($item['line_item_id']) || !isset($item['model_id'])) {
                $fail('Line item id or Invoice id is missing for item ' . ($index + 1));
                return;
            }


            $documentableType = $item['model'] === 'purchase_invoices' ? DocumentableModelEnums::PURCHASE_INVOICE->value : DocumentableModelEnums::VENDOR_BILLS->value;

            $lineItem = LineItem::where('id', $item['line_item_id'])
                ->where('documentable_id', $item['model_id'])
                ->where('documentable_type', $documentableType)
                ->first();

            if ($lineItem && $value > $lineItem->amount) {
                $fail('Debit amount cannot be greater than line item amount');
            }
        };
    }

    /**
     * Validate if debit is in full.
     */
    private function validateDebitInFull(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("items.$index");


            if (!isset($item['line_item_id']) || !isset($item['model_id'])) {
                $fail('Line item id or Invoice id is missing for item ' . ($index + 1));
                return;
            }

            $documentableType = $item['model'] === 'purchase_invoices' ? DocumentableModelEnums::PURCHASE_INVOICE->value : DocumentableModelEnums::VENDOR_BILLS->value;

            $lineItem = LineItem::where('id', $item['line_item_id'])
                ->where('documentable_id', $item['model_id'])
                ->where('documentable_type', $documentableType)
                ->first();

            if ($lineItem && $value && $item['debit_amount'] != $lineItem->amount) {
                $fail('Debit amount not equal to line item amount');
            }
        };
    }
}
