<?php

namespace App\Http\Requests\Company\Accounting\JournalEntry;

use App\Enums\JournalEntryStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class JournalEntryRequest extends FormRequest
{
    // Authorize all users to access this request
    public function authorize()
    {
        return true;
    }

    // Define validation rules
    public function rules()
    {
        $rules = [
            // 'journal_date' => 'required|date', // Ensure journal date is provided and is a valid date
            'status' => ['required', new Enum(JournalEntryStatusEnum::class)],
            'accountEntries' => 'required|array',
            'accountEntries.*.account_id' => 'required|integer|exists:finance_chart_of_accounts,id',
            'accountEntries.*.credit_amount' => 'nullable|min:0', //numeric
            'accountEntries.*.debit_amount' => 'nullable|min:0', //numeric
            'accountEntries.*.description' => 'nullable|string|max:255',
            'accountEntries.*.reference' => 'required|string|max:255',
            'accountEntries.*.transaction_date' => 'required|date',
        ];


        if ($this->isMethod('put') || $this->isMethod('patch')) {
            // You can add more rules specific to update, such as checking for an existing journal entry
            // Example: You may have to check if the journal entry exists before updating or other unique validations
        }

        return $rules;
    }

    // Custom validation messages
    public function messages()
    {
        return [
            // 'journal_date.required' => 'The journal date is required.',
            // 'journal_date.date' => 'The journal date must be a valid date.',
            'status.required' => 'The status field is required.',
            'status.string' => 'The status must be a valid string.',
            'status.in' => 'The status must be one of the following: published, pending, or draft.',
            'accountEntries.*.transaction_date.required' => 'The transaction date is required.',
            'accountEntries.*.transaction_date.date' => 'The transaction date must be a valid date.',
            'accountEntries.*.account_id.required' => 'The account ID is required.',
            'accountEntries.*.account_id.integer' => 'The account ID must be an integer.',
            'accountEntries.*.account_id.exists' => 'Account not selected in one or more entries.',
            // 'accountEntries.*.credit_amount.numeric' => 'The credit amount must be a number.',
            // 'accountEntries.*.debit_amount.numeric' => 'The debit amount must be a number.',
            'accountEntries.*.credit_amount.min' => 'The credit amount must be at least 0.',
            'accountEntries.*.debit_amount.min' => 'The debit amount must be at least 0.',
            // 'accountEntries.*.description.max' => 'The description may not be greater than 255 characters.',
            'accountEntries.*.reference.required' => 'The reference is required.',
            'accountEntries.*.reference.string' => 'The reference must be a valid string.',
            'accountEntries.*.reference.max' => 'The reference may not be greater than 255 characters.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if (isset($this->accountEntries)) {
                foreach ($this->accountEntries as $key => $accountEntry) {
                    $creditAmount = $accountEntry['credit_amount'] ?? null;
                    $debitAmount = $accountEntry['debit_amount'] ?? null;

                    $creditAmount = is_numeric($creditAmount) ? (float)$creditAmount : null;
                    $debitAmount = is_numeric($debitAmount) ? (float)$debitAmount : null;

                    if (!is_null($creditAmount) && !is_null($debitAmount) && $creditAmount > 0 && $debitAmount > 0) {
                        $validator->errors()->add("accountEntries.{$key}.credit_amount", "Both credit_amount and debit_amount cannot be filled at the same time.");
                        $validator->errors()->add("accountEntries.{$key}.debit_amount", "Both credit_amount and debit_amount cannot be filled at the same time.");
                    } elseif ((is_null($creditAmount) || $creditAmount == 0) && (is_null($debitAmount) || $debitAmount == 0)) {
                        $validator->errors()->add("accountEntries.{$key}.credit_amount", "Either credit_amount or debit_amount must be filled.");
                        $validator->errors()->add("accountEntries.{$key}.debit_amount", "Either credit_amount or debit_amount must be filled.");
                    }
                }
            }
        });
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
