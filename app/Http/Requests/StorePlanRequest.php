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
            "short_description" => 'nullable|string',
            "primary_cta_text" => 'nullable|string',
            "primary_link" => 'nullable|string',
            "secondary_cta" => 'nullable|string',
            "secondary_link" => 'nullable|string',
            "default_seat" => 'nullable|numeric',
            "seat_amount" => 'nullable',
            "features" => 'nullable|array',
            "features*" => 'required|string',
            "modules" => 'nullable|array',
            "modules*module_id" => 'required|integer|exists:modules:id',
            "modules*module_functionality_id" => 'required|array',
            "modules*module_functionality_id*" => 'required|integer|exists:module_functionalities:id',
        ];
    }
}
