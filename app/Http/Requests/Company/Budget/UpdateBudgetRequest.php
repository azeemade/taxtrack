<?php

namespace App\Http\Requests\Company\Budget;

use App\Models\FinanceChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBudgetRequest extends FormRequest
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
        // Fetch valid account IDs for budget categories
        $validAccountIds = FinanceChartOfAccount::where(function ($query) {
            // Sales: subCategory slug = 'sales'
            $query->whereHas('subCategory', function ($q) {
                $q->where('slug', 'sales');
            })
                // Cost of Sales: subCategory slug = 'cost-of-sales'
                ->orWhereHas('subCategory', function ($q) {
                    $q->where('slug', 'cost-of-sales');
                })
                // Other Income: accountType slug = 'income', excluding subCategory slug = 'sales'
                ->orWhere(function ($q) {
                    $q->whereHas('accountType', function ($qt) {
                        $qt->where('slug', 'income');
                    })->whereHas('subCategory', function ($qs) {
                        $qs->where('slug', '!=', 'sales');
                    });
                })
                // Expenses: accountCategory slug = 'operating-expenses', excluding cost-of-sales and income-tax-payable
                ->orWhere(function ($q) {
                    $q->whereHas('accountCategory', function ($qc) {
                        $qc->where('slug', 'operating-expenses');
                    })->whereDoesntHave('subCategory', function ($qs) {
                        $qs->whereIn('slug', ['cost-of-sales', 'income-tax-payable']);
                    });
                })
                // Income Tax: subCategory slug = 'income-tax-payable'
                ->orWhereHas('subCategory', function ($q) {
                    $q->where('slug', 'income-tax-payable');
                });
        })
            ->where('company_id', auth()->user()->company_id ?? 1) // Scope to company
            ->pluck('id')
            ->toArray();


        return [
            'name' => 'required|string|max:255|unique:budgets,name' . ($this->isMethod('PUT') ? ',' . $this->route('budget') : ''),
            'status' => 'required|in:active,draft',
            'start_date' => 'required|date',
            'cycle' => 'required|in:monthly,quarterly,annually',
            'duration' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'line_items.*' => 'required|array',
            'line_items.*.id' => 'sometimes|integer|exists:budget_items,id',
            // 'line_items.*.name' => 'required|string|max:255',
            'line_items.*.category_id' => 'nullable|integer|exists:categories,id',
            // 'line_items.*.account_id' => ['required', 'integer', Rule::in($validAccountIds)],
            'line_items.*.account_id' => 'required|integer|exists:finance_chart_of_accounts,id',
            'line_items.*.periods' => 'required|array',
            'line_items.*.periods.*.id' => 'sometimes|integer|exists:budget_periods,id',
            'line_items.*.periods.*.month' => 'required|string|in:january,february,march,april,may,june,july,august,september,october,november,december',
            'line_items.*.periods.*.year' => 'required|numeric|min:0',
            'line_items.*.periods.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'line_items.*.account_id' => 'The account ID must correspond to a valid budget account.',
            'line_items.*.periods.*.month' => 'The month must be a valid lowercase month name (e.g., january).',
            'line_items.*.periods.*.year' => 'The year must be a valid four-digit year.',
        ];
    }
}
