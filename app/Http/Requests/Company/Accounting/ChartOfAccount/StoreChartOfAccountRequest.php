<?php

namespace App\Http\Requests\Company\Accounting\ChartOfAccount;

use Illuminate\Foundation\Http\FormRequest;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'account_sub_category_id' => 'required|exists:finance_account_sub_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'reference_code' => 'nullable|string|max:100',
            'opening_balance' => 'nullable|numeric|min:0',
            'balance_date' => 'nullable|date',
        ];
    }

    public function messages()
    {
        return [
            'account_sub_category_id.required' => 'The sub-category field is required.',
            'account_sub_category_id.exists' => 'The selected sub-category does not exist.',
            'name.required' => 'The account name is required.',
            'name.max' => 'The name must not exceed 255 characters.',
            'description.max' => 'The description must not exceed 500 characters.',
            'reference_code.max' => 'The reference code must not exceed 100 characters.',
            'opening_balance.numeric' => 'The opening balance must be a valid number.',
            'opening_balance.min' => 'The opening balance must be at least 0.',
            'balance_date.date' => 'The balance date must be a valid date.',
        ];
    }
}
