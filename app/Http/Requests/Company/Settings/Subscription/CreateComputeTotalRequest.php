<?php

namespace App\Http\Requests\Company\Settings\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CreateComputeTotalRequest extends FormRequest
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
            'duration' => 'required|string|in:monthly,yearly',
            'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
            'additional_users_count' => 'nullable|integer|min:0'
        ];
    }
}
