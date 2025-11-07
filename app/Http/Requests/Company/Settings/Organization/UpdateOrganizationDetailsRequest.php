<?php

namespace App\Http\Requests\Company\Settings\Organization;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationDetailsRequest extends FormRequest
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
            "name" => 'sometimes|string|max:100',
            "logo" => 'sometimes|nullable|string',
            "industry" => 'sometimes|string|max:50',
            "tax_type" => 'sometimes|string|max:50',
            "vat_date" => 'sometimes|string|max:50',
            "organization_type" => 'sometimes|string|max:20',
            "registration_id" => 'sometimes|string|max:20',
            "description" => 'sometimes|nullable|string|max:250',
            "postal_address_information" => 'sometimes|nullable|array',
            "postal_address_information.country_id" => 'sometimes|nullable|integer|exists:countries,id',
            "postal_address_information.address" => 'sometimes|nullable|string|max:250',
            "postal_address_information.state_id" => 'sometimes|nullable|integer|exists:states,id',
            "postal_address_information.city_id" => 'sometimes|nullable|integer|exists:cities,id',
            "physical_address_information" => 'sometimes|array',
            "physical_address_information.country_id" => 'sometimes|integer|exists:countries,id',
            "physical_address_information.address" => 'sometimes|nullable|string|max:250',
            "physical_address_information.state_id" => 'sometimes|nullable|integer|exists:states,id',
            "physical_address_information.city_id" => 'sometimes|nullable|integer|exists:cities,id',
            "phone_country_code" => 'sometimes|string|max:5',
            "phone_number" => 'nullable',
            "secondary_phone_country_code" => 'sometimes|nullable|string|max:5',
            "secondary_phone_number" => 'sometimes|nullable|string|max:40',
            "fax" => 'sometimes|nullable|string|max:5',
            "email" => 'sometimes|string|max:250',
            "secondary_email" => 'sometimes|nullable|string|max:250',
            "social_media" => 'sometimes|nullable|array',
            "social_media.twitter" => 'sometimes|string|max:100',
            "social_media.instagram" => 'sometimes|string|max:100',
            "social_media.whatsapp" => 'sometimes|string|max:100',
            "terms_and_conditions" => 'sometimes|nullable|string|max:250',
        ];
    }
}
