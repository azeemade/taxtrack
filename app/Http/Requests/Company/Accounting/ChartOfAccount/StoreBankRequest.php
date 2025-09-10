<?php

namespace App\Http\Requests\Company\Accounting\ChartOfAccount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBankRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // 'name' => 'required|string|max:255',
            'bank_id' => 'required|exists:banks,id',
            'description' => 'nullable|string|max:500',
            'holder_name' => 'nullable|string|max:500',
            'account_type' => 'nullable|string|max:500',
            'account_number' => 'nullable|string|max:500',
            'currency' => 'nullable|string|max:500',
            'reference_code' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'balance_date' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'bank_id.required' => 'The bank field is required.',
            'bank_id.exists' => 'The selected bank does not exist.',
            'name.required' => 'The account name is required.',
            'name.max' => 'The name must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 500 characters.',
            'reference_code.max' => 'The reference code must not exceed 100 characters.',
            'opening_balance.numeric' => 'The opening balance must be a valid number.',
            'opening_balance.min' => 'The opening balance must be at least 0.',
            'balance_date.date' => 'The balance date must be a valid date.',
        ];
    }

    // Override failedValidation method to return custom error messages
    protected function failedValidation(Validator $validator)
    {
        // Get the first error message
        $firstError = $validator->errors()->first();

        throw new HttpResponseException(
            response()->json([
                'error' => true,
                'message' => "Validation failed: $firstError"
            ], 400)
        );
    }
}
