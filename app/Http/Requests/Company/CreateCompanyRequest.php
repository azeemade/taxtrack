<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
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
            "name" => 'required|string|unique:companies,name',
            "address" => 'required|string',
            "phone_number" => 'required|string|unique:companies,phone_number',
            "domain" => 'required|string|unique:companies,domain',
            "status" => 'sometimes|in:approved,pending,declined'
        ];
    }
}
