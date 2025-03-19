<?php

namespace App\Http\Requests\Company\Budget;

use Illuminate\Foundation\Http\FormRequest;

class CreateBudgetRequest extends FormRequest
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
            'name' => 'required|string|unique:budgets,name|max:255',
            'status' => 'required|in:active,draft',
            'start_date' => 'required|date',
            'cycle' => 'required|in:monthly,quarterly,annually',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'line_items.*' => 'required|array',
            'line_items.*.id' => 'sometimes|integer|exists:budget_items,id',
            'line_items.*.name' => 'required|string|max:255',
            'line_items.*.category_id' => 'required|integer|exists:categories,id',
            'line_items.*.periods' => 'required|array',
            'line_items.*.periods.*.id' => 'sometimes|integer|exists:budget_periods,id',
            'line_items.*.periods.*.month' => 'required|string|in:january,february,march,april,may,june,july,august,september,october,november,december',
            'line_items.*.periods.*.year' => 'required|numeric|min:0',
            'line_items.*.periods.*.amount' => 'required|numeric|min:0',
        ];
    }
}
