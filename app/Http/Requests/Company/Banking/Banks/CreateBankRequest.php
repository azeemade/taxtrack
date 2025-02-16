<?php

namespace App\Http\Requests\Company\Banking\Banks;

use Illuminate\Foundation\Http\FormRequest;

class CreateBankRequest extends FormRequest
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
            "bank_id" => 'required|integer|exists:banks,id',
            "holder_name" => 'required|string|max:225',
            "account_type" => 'required|string|max:225',
            "account_number" => 'required|string|digits_between:4,34',
            "currency_id" => 'required|integer|exists:currencies,id',
            "opening_balance" => 'nullable|numeric|min:0',
            "opening_balance_as_at" => 'nullable|date:Y-m-d',
        ];
    }
}
