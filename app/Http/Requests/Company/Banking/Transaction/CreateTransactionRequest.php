<?php

namespace App\Http\Requests\Company\Banking\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Set this to true to allow any authenticated user to make this request.
        // You can add more complex authorization logic here if needed,
        // for example, checking if the user belongs to the correct company.
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
            'payment_type' => ['required', 'string', Rule::in(['payment', 'receipt'])],
            'bank_account_id' => ['required', 'integer', 'exists:finance_chart_of_accounts,id'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'pending', 'published', 'unpublished'])],

            // Rules for the financeTransactions array itself
            'financeTransactions' => ['required', 'array', 'min:1'],

            // Rules for each object within the financeTransactions array
            'financeTransactions.*.transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'financeTransactions.*.account_id' => ['required', 'integer', 'exists:finance_chart_of_accounts,id'],
            'financeTransactions.*.transactionID' => ['nullable', 'string', 'max:255'],
            'financeTransactions.*.referenceID' => ['nullable', 'string', 'max:255'],
            'financeTransactions.*.description' => ['required', 'string', 'max:500'],
            'financeTransactions.*.amount' => ['required', 'numeric', 'gt:0'],
            'financeTransactions.*.mode_of_payment' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get the custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_type.required' => 'The payment type is required.',
            'payment_type.in' => 'The payment type must be either "payment" or "receipt".',
            'bank_account_id.required' => 'The main bank account ID is required.',
            'bank_account_id.exists' => 'The selected bank account does not exist.',
            'financeTransactions.required' => 'At least one transaction item is required.',
            'financeTransactions.min' => 'You must provide at least one transaction item.',

            // Messages for the nested array items
            'financeTransactions.*.transaction_date.required' => 'The transaction date is required for all items.',
            'financeTransactions.*.transaction_date.date' => 'A valid transaction date must be provided.',
            'financeTransactions.*.transaction_date.before_or_equal' => 'The transaction date cannot be in the future.',
            'financeTransactions.*.account_id.required' => 'An account ID is required for all items.',
            'financeTransactions.*.account_id.exists' => 'An invalid account ID was provided in one of the items.',
            'financeTransactions.*.description.required' => 'A description is required for all items.',
            'financeTransactions.*.type.required' => 'The transaction type (Credit/Debit) is required for all items.',
            'financeTransactions.*.amount.required' => 'An amount is required for all items.',
            'financeTransactions.*.amount.gt' => 'The amount for all items must be greater than zero.',
            'financeTransactions.*.mode_of_payment.required' => 'The mode of payment is required for all items.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        // Get the first error message
        $firstError = $validator->errors()->first();

        throw new HttpResponseException(
            response()->json([
                'error' => true,
                'message' => "Validation failed: $firstError"
            ], 422)
        );
    }
}