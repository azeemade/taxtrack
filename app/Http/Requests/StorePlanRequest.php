<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            "title" => 'required|string',
            "monthly_fee" => 'required|numeric',
            "yearly_fee" => 'required|numeric',
            "short_description" => 'required|string',
            "primary_cta_text" => 'required|string',
            "primary_link" => 'required|string',
            "secondary_cta" => 'required|string',
            "secondary_link" => 'required|string',
            "features" => 'nullable|array',
            "features*" => 'required|string'
        ];
    }
}
