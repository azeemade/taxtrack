<?php

namespace App\Http\Requests\Company\Purchase\Payment;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'model' => 'sometimes|string|in:vendor_bills,purchase_invoices,invoices',
            'model_id' => 'sometimes|integer|exists:' . $this->model . ',id',
            'paid_on' => 'nullable|date_format:Y-m-d',
            'amount_paid' => 'required|numeric|min:0.00',
            'paymentID' => 'nullable|string|max:20',
            'payment_method_id' => 'required|integer|exists:finance_chart_of_accounts,id',
            'payment_proof' => 'nullable|string',
            'attachments' => 'nullable|string',
            'additional_notes' => 'nullable|string|max:250'
        ];
    }
}
