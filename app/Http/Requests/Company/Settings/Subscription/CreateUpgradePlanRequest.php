<?php

namespace App\Http\Requests\Company\Settings\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class CreateUpgradePlanRequest extends FormRequest
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
            // 'additional_charge' => 'nullable|numeric|min:0.00',
            // 'tax' => 'nullable|numeric|min:0.00',
            'additional_users_count' => 'nullable|integer|min:0',
            // 'sub_total' => 'required|numeric|min:0.00',
            // 'total' => 'required|numeric|min:0.00',
            'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
            'save_card' => 'required',
            // 'use_credit_balance' => 'nullable|boolean',
            // 'credit_balance' => 'nullable|decimal|min:0.00',
            'upgrade' => 'nullable|boolean',
            'card_number' => 'required_if:save_card,true|string',
            'name' => 'required_if:save_card,true|string',
            'expiry_date' => 'required_if:save_card,true|string',
            'cvv' => 'required_if:save_card,true|string',
        ];
    }
}
