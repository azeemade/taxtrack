<?php

namespace App\Http\Requests\Company;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class CreateBadDebtRequest extends FormRequest
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
            'date_issued' => 'required|date_format:Y-m-d',
            'amount' => ['required', 'numeric', 'min:0.00', $this->validateBadDebtAmount()],
        ];
    }



    private function validateBadDebtAmount(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $invoice = $this->route('invoice');
            $amountDue = Invoice::find($invoice->id)?->amount_due;

            if ($value > $amountDue) {
                $fail('Amount to be write off cannot be greater than the amount due');
            }
        };
    }
}
