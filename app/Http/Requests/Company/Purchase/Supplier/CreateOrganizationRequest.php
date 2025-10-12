<?php

namespace App\Http\Requests\Company\Purchase\Supplier;

use App\Models\CompanyContactPerson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
        $rules = [
            "vendor_name" => 'required|string|max:225',
            "supplier_reference" => 'nullable|string',
            "primary_email" => 'nullable|string',
            "primary_phone_number" => 'nullable|string',
            "business_registration_number" => 'nullable|string',
            "vat_number" => 'nullable|string',
            "industry" => 'nullable|string',
            "business_type" => 'required|string|in:sole-proprietorship,partnership,corporation',
            "employee_count" => 'nullable|integer',
            "image" => 'nullable|string',
            "special_instruction" => 'nullable|string',
            "payment_term" => 'nullable|integer',
            "currency_id" => 'nullable|integer',
            "bank_name" => 'nullable|string',
            "bank_account_number" => 'nullable|string',
            "bank_identification_code" => 'nullable|string',
            "primary_phone_ext" => 'nullable|string|exists:countries,phone_code',
            "address" => 'nullable|string',
            "terms_and_conditions" => 'nullable|string',
            "country_id" => 'nullable|integer|exists:countries,id',
            "city_id" => 'nullable|integer|exists:cities,id',
            "state_id" => 'nullable|integer|exists:states,id',
            "county" => 'nullable|string',
            "contact_persons" => 'required|array',
            "contact_persons.*.id" => ['sometimes', 'integer', 'exists:company_contact_people,id', function ($attribute, $value, $fail) {
                $person = CompanyContactPerson::where('company_id', Auth::user()?->company?->id)
                    ->where('id', $value)
                    ->first();
                if (!$person) {
                    $fail('Contact person not found.');
                }
            }],
            "contact_persons.*.full_name" => 'required|string',
            "contact_persons.*.primary_email" => 'nullable|string',
            "contact_persons.*.secondary_email" => 'nullable|string',
            "contact_persons.*.primary_phone_number" => 'nullable|string',
            "contact_persons.*.secondary_phone_number" => 'nullable|string',
            "contact_persons.*.country_id" => 'nullable|integer',
            "contact_persons.*.state_id" => 'nullable|integer',
            "contact_persons.*.city_id" => 'nullable|integer',
            "contact_persons.*.county" => 'nullable|string',
            "contact_persons.*.post_code" => 'nullable|string',
            "contact_persons.*.primary_address" => 'nullable|string',
            "contact_persons.*.secondary_address" => 'nullable|string'
        ];

        if ($this->method() == 'PUT') {
            $rules["supplier_reference"] = 'nullable|string|max:10';
        }

        return $rules;
    }
}
