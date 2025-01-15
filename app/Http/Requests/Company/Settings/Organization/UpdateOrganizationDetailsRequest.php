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
            "logo" => 'nullable|string',
            "name" => 'required|string|max:100',
            "industry" => 'required|string|max:50',
            "organization_type" => 'nullable|string|max:20',
            "registration_id" => 'nullable|string|max:20',
            "description" => 'nullable|string|max:250',
            "postal_address_information" => 'required|array',
            "postal_address_information.country_id" => 'required|integer|exists:countries,id',
            "postal_address_information.address" => 'required|string|max:250',
            "postal_address_information.state_id" => 'required|integer|exists:states,id',
            "postal_address_information.city_id" => 'required|integer|exists:cities,id',
            "physical_address_information" => 'required|array',
            "physical_address_information.country_id" => 'required|integer|exists:countries,id',
            "physical_address_information.address" => 'required|string|max:250',
            "physical_address_information.state_id" => 'required|integer|exists:states,id',
            "physical_address_information.city_id" => 'required|integer|exists:cities,id',
            "phone_country_code" => 'nullable|string|max:5',
            "phone_number" => 'nullable|string|max:40',
            "secondary_phone_country_code" => 'nullable|string|max:5',
            "secondary_phone_number" => 'nullable|string|max:40',
            "fax" => 'nullable|string|max:5',
            "email" => 'nullable|string|max:250',
            "secondary_email" => 'nullable|string|max:250',
            "social_media" => 'nullable|array',
            "social_media.twitter" => 'nullable|string|max:100',
            "social_media.instagram" => 'nullable|string|max:100',
            "social_media.whatsapp" => 'nullable|string|max:100',
        ];
    }
}
