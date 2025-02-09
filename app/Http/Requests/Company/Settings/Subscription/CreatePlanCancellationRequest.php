<?php

namespace App\Http\Requests\Company\Settings\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanCancellationRequest extends FormRequest
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
            'effective_from' => 'required|string|in:instant,end_billing_period',
            'reason' => 'required|array',
            'additional_information' => 'nullable|string|max:250'
        ];
    }
}
