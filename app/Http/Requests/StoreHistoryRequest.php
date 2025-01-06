<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHistoryRequest extends FormRequest
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
            "billed_per" => 'required|string',
            "amount" => 'required|numeric',
            "end_date" => 'required|date',
            "payment_type" => 'required|string',
            "paid_via" => 'required|string',
            "subscriber_id" => 'required|string',
            "subscription_plan_id" => 'required|string',
        ];
    }
}
