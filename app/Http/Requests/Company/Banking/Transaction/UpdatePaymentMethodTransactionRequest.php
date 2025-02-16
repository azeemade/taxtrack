<?php

namespace App\Http\Requests\Company\Banking\Transaction;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodTransactionRequest extends FormRequest
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
            "paid_on" => "required|date:Y-m-d",
            "amount_paid" => "required|numeric",
            "paymentID" => "required|string|max:20",
            "referenceID" => "nullable|string|max:20",
            "payment_type" => "required|string|in:debit,credit",
            "additional_notes" => "nullable|string|max:225",
        ];
    }
}
