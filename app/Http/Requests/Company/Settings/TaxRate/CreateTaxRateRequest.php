<?php

namespace App\Http\Requests\Company\Settings\TaxRate;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaxRateRequest extends FormRequest
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
            'name' => 'required|string|max:225|unique:tax_rates,name',
            'display_name' => 'required|string|max:24|unique:tax_rates,name',
            'rate' => 'required|min:0.00',
            'non_recoverable' => 'nullable',
            'has_components' => 'nullable',
            'components' => 'required_if:has_components,true|array',
            'components.*.name' => 'nullable|string|max:225',
            'components.*.rate' => 'nullable|min:0.00',
            'components.*.non_recoverable' => 'nullable',
            'components.*.compound' => 'nullable',
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:225|unique:tax_rates,name,' . $this->route('id');
            $rules['display_name'] = 'required|string|max:24|unique:tax_rates,name,' . $this->route('id');
        }

        return $rules;
    }
}
