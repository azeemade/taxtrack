<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AddCompanyRequest extends FormRequest
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
            'companies' => 'nullable|array',
            'companies.*.name' => 'required|string|unique:companies,name',
            'companies.*.address' => 'required|string',
            'companies.*.country_id' => 'required|exists:countries,id',
            'companies.*.industry' => 'required|string',
            'companies.*.tax_id' => 'nullable|string',
            'companies.*.registration_id' => 'nullable|string',
            'companies.*.fiscal_year_start' => 'nullable|date_format:m-d',
            'companies.*.fiscal_year_end' => 'nullable|date_format:m-d',
        ];
    }
}
