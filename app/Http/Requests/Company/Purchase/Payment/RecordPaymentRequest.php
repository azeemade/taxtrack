<?php

namespace App\Http\Requests\Company\Purchase\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model'      => 'sometimes|string|in:vendor_bills,purchase_invoices,invoices',
            'model_id'   => 'sometimes|integer|exists:' . $this->model . ',id',
            'paid_on'    => 'nullable|date_format:Y-m-d',
            'amount_paid'=> 'required|numeric|min:0.01',

            // ✅ REQUIRED bank account from chart of accounts
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('finance_chart_of_accounts', 'id'),
            ],

            // (optional)
            'paymentID'        => 'nullable|string|max:20',
            'payment_proof'    => 'nullable|string',
            'attachments'      => 'nullable|string',
            'additional_notes' => 'nullable|string|max:250',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $bankId = $this->input('bank_account_id');
            if (!$bankId) return;

            // Must belong to same company & be under subcategory slug 'cash-and-bank'
            $companyId = auth()->user()->current_company_id;

            $ok = DB::table('finance_chart_of_accounts as a')
                ->join('finance_account_sub_categories as sc', 'sc.id', '=', 'a.account_sub_category_id')
                ->join('finance_account_categories as c', 'c.id', '=', 'sc.account_category_id')
                ->join('finance_account_types as t', 't.id', '=', 'c.account_type_id')
                ->where('a.id', $bankId)
                ->where('a.company_id', $companyId)
                ->where('sc.slug', 'cash-and-bank')
                ->exists();

            if (!$ok) {
                $v->errors()->add('bank_account_id', 'Selected bank account must be a Cash & Bank account for this company.');
            }
        });
    }
}
