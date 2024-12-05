<?php

namespace App\Http\Requests\Company\Sales\CreditNotes;

use App\Enums\DocumentableModelEnums;
use App\Models\Customer;
use App\Models\LineItem;
use Illuminate\Foundation\Http\FormRequest;

class CreateCreditNoteRequest extends FormRequest
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
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
                $this->validateCustomerCurrency(),
            ],
            'issue_date' => 'required|date_format:Y-m-d',
            'additional_referenceID' => 'nullable|string|max:20',
            'save_status' => 'required|in:save,send,draft',
            'invoices' => 'required|array|min:1',
            'invoices.*.invoice_id' => 'required|integer|exists:invoices,id',
            'invoices.*.line_item_id' => [
                'required',
                'integer',
                'exists:line_items,id',
                $this->validateLineItem(),
            ],
            'invoices.*.credit_amount' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateCreditAmount(),
            ],
            'invoices.*.credit_in_full' => [
                'required',
                'boolean',
                $this->validateCreditInFull(),
            ],
        ];
    }

    /**
     * Validate the customer's currency.
     */
    private function validateCustomerCurrency(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $customerCurrency = Customer::where('currency_id', $value)
                ->where('id', $this->input('customer_id'))
                ->exists();

            if (!$customerCurrency) {
                $fail('Invalid customer currency selected');
            }
        };
    }

    /**
     * Validate the line item.
     */
    private function validateLineItem(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("invoices.$index");

            if (!isset($item['invoice_id'])) {
                $fail('Invoice id is missing for item ' . ($index + 1));
                return;
            }

            $lineItem = LineItem::where('id', $value)
                ->where('documentable_id', $item['invoice_id'])
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->exists();

            if (!$lineItem) {
                $fail('Line item ' . ($index + 1) . ' is invalid');
            }
        };
    }

    /**
     * Validate the credit amount.
     */
    private function validateCreditAmount(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("invoices.$index");


            if (!isset($item['line_item_id']) || !isset($item['invoice_id'])) {
                $fail('Line item id or Invoice id is missing for item ' . ($index + 1));
                return;
            }

            $lineItem = LineItem::where('id', $item['line_item_id'])
                ->where('documentable_id', $item['invoice_id'])
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->first();

            if ($lineItem && $value > $lineItem->amount) {
                $fail('Credit amount cannot be greater than line item amount');
            }
        };
    }

    /**
     * Validate if credit is in full.
     */
    private function validateCreditInFull(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("invoices.$index");


            if (!isset($item['line_item_id']) || !isset($item['invoice_id'])) {
                $fail('Line item id or Invoice id is missing for item ' . ($index + 1));
                return;
            }

            $lineItem = LineItem::where('id', $item['line_item_id'])
                ->where('documentable_id', $item['invoice_id'])
                ->where('documentable_type', DocumentableModelEnums::INVOICE->value)
                ->first();

            if ($lineItem && $value && $item['credit_amount'] != $lineItem->amount) {
                $fail('Credit amount not equal to line item amount');
            }
        };
    }
}
