<?php

namespace App\Http\Requests\Company\Settings\DocumentSettings;

use Illuminate\Foundation\Http\FormRequest;

class AddDefaultSettingsRequest extends FormRequest
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
            'bills_default_due_date_number' => 'sometimes|nullable|integer',
            'bills_default_due_date_condition' => 'sometimes|nullable|string|in:following_month,after_bill_date,after_end_of_bill_month,current_month',
            'sales_invoice_default_due_date_number' => 'sometimes|nullable|integer',
            'sales_invoice_default_due_date_condition' => 'sometimes|nullable|string|in:following_month,after_bill_date,after_end_of_bill_month,current_month',
            'invoice_prefix' => 'sometimes|nullable|string|max:4',
            'invoice_prefix_number' => 'sometimes|nullable|string|max:6',
            'credit_note_prefix' => 'sometimes|nullable|string|max:4',
            'credit_note_prefix_number' => 'sometimes|nullable|string|max:6',
            'debit_note_prefix' => 'sometimes|nullable|string|max:4',
            'debit_note_prefix_number' => 'sometimes|nullable|string|max:6',
            'purchase_order_prefix' => 'sometimes|nullable|string|max:4',
            'purchase_order_prefix_number' => 'sometimes|nullable|string|max:6',
            'quote_prefix' => 'sometimes|nullable|string|max:4',
            'quote_prefix_number' => 'sometimes|nullable|string|max:6',
            'receipt_prefix' => 'sometimes|nullable|string|max:4',
            'receipt_prefix_number' => 'sometimes|nullable|string|max:6',
            'quote_expiration_number' => 'sometimes|nullable|integer',
            'quote_expiration_condition' => 'sometimes|nullable|string|in:following_month,after_bill_date,after_end_of_bill_month,current_month',
        ];
    }
}
