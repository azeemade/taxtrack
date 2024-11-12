<?php

namespace App\Http\Requests\Company\Sales\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateIndividualRequest extends FormRequest
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
            "full_name" => 'required|string|max:225',
            "display_name" => 'required|string|max:225',
            "salutation" => 'nullable|string|max:225',
            "category_id" => 'nullable|integer',
            "customer_type" => 'required|in:business,individual',
            "currency_id" => 'required|integer',
            "image" => 'nullable|string',
            "primary_phone_number" => 'required|string',
            "secondary_phone_number" => 'nullable|string',
            "primary_email" => 'required|string',
            "secondary_email" => 'nullable|string',
            "country_id" => 'nullable|integer',
            "city_id" => 'nullable|integer',
            "primary_address" => 'nullable|string',
            "secondary_address" => 'nullable|string',
            "zip_code" => 'nullable|string',
        ];
    }
}
