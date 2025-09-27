<?php

namespace App\Http\Requests\Company\Banking\Cards;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CreateCardRequest extends FormRequest
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
            "issuer_number" => 'required|string|max:16|min:16|unique:card_accounts,issuer_number,NULL,id,company_id,' . Auth::user()->company->id,
            "holder_name" => 'required|string|max:225',
            "cvv" => 'sometimes|string|max:3',
            "expiration_date" => 'required|date:Y-m-d',
            "billing_address" => 'nullable|string|max:500',
            "billing_postal_code" => 'nullable|string|max:20',
            "issuing_bank_id" => 'nullable|integer|exists:finance_chart_of_accounts,id',
            "billing_country_id" => 'required|integer|exists:countries,id',
            "currency_id" => 'required|integer|exists:currencies,id',
            "card_brand_id" => 'required|integer|exists:card_brands,id'
        ];

        if ($this->method() == 'PUT') {
            $rules['issuer_number'] = 'required|string|max:16|min:16';
        }
        return $rules;
    }
}
