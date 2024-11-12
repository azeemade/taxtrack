<?php

namespace App\Http\Requests\Company\Sales\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
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
            "company_name" => 'required|string|max:225',
            "primary_email" => 'required|string',
            "primary_phone_number" => 'required|string',
            "business_registration_number" => 'nullable|string',
            "vat_number" => 'nullable|string',
            "industry" => 'required|string',
            "business_type" => 'required|string|in:proprietorship,partnership,corporation',
            "employee_count" => 'required|integer',
            "image" => 'nullable|string',
            "special_instruction" => 'nullable|string',
            "payment_term" => 'nullable|string',
            "currency_id" => 'required|integer',
            "contact_persons" => 'required|array',
            "contact_persons.*.full_name" => 'required|string',
            "contact_persons.*.primary_email" => 'required|string',
            "contact_persons.*.secondary_email" => 'nullable|string',
            "contact_persons.*.primary_phone_number" => 'required|string',
            "contact_persons.*.secondary_phone_number" => 'nullable|string',
            "contact_persons.*.country_id" => 'required|integer',
            "contact_persons.*.state_id" => 'required|integer',
            "contact_persons.*.city_id" => 'required|integer',
            "contact_persons.*.post_code" => 'nullable|string',
            "contact_persons.*.primary_address" => 'nullable|string',
            "contact_persons.*.secondary_address" => 'nullable|string'
        ];
    }
}
