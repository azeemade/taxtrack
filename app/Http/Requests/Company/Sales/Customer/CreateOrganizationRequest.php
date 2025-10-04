<?php

namespace App\Http\Requests\Company\Sales\Customer;

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
        return [
            "company_name" => 'required|string|max:225',
            "primary_email" => 'required|string',
            "primary_phone_number" => 'nullable|string',
            "business_registration_number" => 'nullable|string',
            "vat_number" => 'nullable|string',
            "vat_date" => 'nullable|string',
            "tax_type" => 'nullable|string',
            "industry" => 'nullable|string',
            "business_type" => 'nullable|string|in:limited-liability-partnership,partnership,corporation,sole-proprietorship,limited-company',
            "employee_count" => 'nullable|integer',
            "image" => 'nullable|string',
            "special_instruction" => 'nullable|string',
            "payment_term" => 'nullable|integer',
            "currency_id" => 'nullable|integer',
            "phone_ext" => 'nullable|string',
            "address" => 'nullable|string',
            "country_id" => 'nullable|integer',
            "city_id" => 'nullable|integer',
            "state_id" => 'nullable|integer',
            "county" => 'nullable|string',
            "terms_and_conditions" => 'nullable|string',
            "contact_persons" => 'nullable|array',
            "contact_persons.*.id" => ['sometimes', 'nullable', 'exists:company_contact_people,id', function ($attribute, $value, $fail) {
                $person = CompanyContactPerson::where('company_id', Auth::user()?->company?->id)
                    ->where('id', $value)
                    ->first();
                if (!$person) {
                    $fail('Contact person not found.');
                }
            }],
            "contact_persons.*.full_name" => 'nullable|string',
            "contact_persons.*.primary_email" => 'required|string',
            "contact_persons.*.secondary_email" => 'nullable|string',
            "contact_persons.*.primary_phone_number" => 'nullable|string',
            "contact_persons.*.secondary_phone_number" => 'nullable|string',
            "contact_persons.*.country_id" => 'required|integer',
            "contact_persons.*.post_code" => 'nullable|string',
            "contact_persons.*.primary_address" => 'nullable|string',
            "contact_persons.*.secondary_address" => 'nullable|string'
        ];
    }
}
