<?php

namespace App\Http\Requests\Company\Sales\Customer;

use App\Models\CompanyContactPerson;
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
            "currency_id" => 'required|integer|exists:currencies,id',
            "image" => 'nullable|string',
            "phone_ext" => 'required|string||exists:countries,phone_code',
            "primary_phone_number" => 'required|string',
            "secondary_phone_number" => 'nullable|string',
            "primary_email" => 'required|string|email',
            "secondary_email" => 'nullable|string|email',
            "country_id" => 'nullable|integer|exists:countries,id',
            "city_id" => 'nullable|integer|exists:cities,id',
            "primary_address" => 'nullable|string',
            "secondary_address" => 'nullable|string',
            "zip_code" => 'nullable|string',
            "contact_person_id" => ['sometimes', 'required', 'integer', 'exists:company_contact_people,id', function ($attribute, $value, $fail) {
                $person = CompanyContactPerson::where('company_id', auth()->user()?->company?->id)
                    ->where('id', $value)
                    ->first();
                if (!$person) {
                    $fail('Contact person not found.');
                }
            }],
        ];
    }
}
