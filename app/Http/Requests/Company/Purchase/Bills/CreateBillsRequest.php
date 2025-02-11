<?php

namespace App\Http\Requests\Company\Purchase\Bills;

use App\Traits\DocumentValidationTrait;
use Illuminate\Foundation\Http\FormRequest;

class CreateBillsRequest extends FormRequest
{
    use DocumentValidationTrait;
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
            'vendor_bill_due_date' => 'required|date_format:Y-m-d',
            'vendor_billID' => 'sometimes|string',
            'vendor_id' => 'required|integer|exists:vendors,id',
            'purchase_invoice_id' => 'required|integer|exists:purchase_invoices,id',
            'attachments' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string|max:250',
            'additional_comment' => 'nullable|string|max:250',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateSubTotal()
            ],
            'vendor_bill_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateDocumentTotal()
            ],
            'recurring_start_date' => 'sometimes|required_if:save_status,recur|date_format:Y-m-d',
            'recurring_end_date' => 'sometimes|required_if:save_status,recur|date_format:Y-m-d',
            'repeat' => 'sometimes|required_if:save_status,recur|integer',
            'repeat_period' => 'sometimes|required_if:save_status,recur|string|in:month,day,week,year',
            'save_status' => 'required|string|in:draft,save,send,recur',
            'line_items' => 'required|array',
            'line_items.*.id' => 'sometimes|integer|exists:line_items,id',
            'line_items.*.item_details' => 'required|string|max:50',
            'line_items.*.category_id' => 'nullable|integer|exists:categories,id',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.price' => 'required|numeric|min:0.00',
            'line_items.*.total_unit_price' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateTotalUnitPrice()
            ],
            'line_items.*.discount' => 'nullable|numeric|min:0',
            'line_items.*.vat' => 'nullable|numeric|min:0',
            'line_items.*.amount' => [
                'required',
                'numeric',
                'min:0',
                $this->validateLineItemAmount()
            ],
        ];
    }
}
