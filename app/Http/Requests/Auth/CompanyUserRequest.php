<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CompanyUserRequest extends FormRequest
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
            'companies' => 'required|array',
            'companies.*.name' => 'required|string|unique:companies,name||max:250',
            'companies.*.address' => 'required|string||max:250',
            'companies.*.country_id' => 'required|exists:countries,id',
            'companies.*.industry' => 'required|string||max:250',
            'companies.*.tax_id' => 'nullable|string|max:20',
            'companies.*.tax_type' => 'nullable|string',
            'companies.*.vat_date' => 'nullable|string',
            'companies.*.registration_id' => 'nullable|string',
            'companies.*.fiscal_year_start' => 'nullable|date_format:m-d',
            'companies.*.fiscal_year_end' => 'nullable|date_format:m-d',
            'companies.*.subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'companies.*.provider_payment_method_id' => 'nullable|string',
            'companies.*.save_card' => 'nullable',
            'companies.*.additional_users_count' => 'nullable|numeric',
            'companies.*.duration' => 'nullable|in:monthly,yearly',
            'users' => 'nullable|array',
            'users.*.name' => 'required|string|max:250',
            'users.*.roles' => 'nullable|array',
            // 'users.*.roles.*' => 'required|integer|exists:roles,id',
            'users.*.roles.*' => 'required|string|max:50',
            'users.*.company' => 'nullable|array',
            'users.*.company.*' => 'required|string|max:50',
            'users.*.email' => [
                'required',
                'string',
                'email',
                'max:250',
                function ($attribute, $value, $fail) {
                    foreach ($this->users as $user) {
                        $userCheck = User::where('email', $user['email'])
                            ->first();
                        $companyField = $user['company'] ?? $user['company_id'];
                        $company = Company::where('name', $companyField)
                            ->orWhere('id', $companyField)->first();
                        if ($userCheck && $company) {
                            $client = Client::whereIn('company_id', $company->id)
                                ->where('user_id', $userCheck['id'])
                                ->first();
                            if ($client) {
                                $fail('User with ' . $value . ' already exist in this company.');
                            }
                        }
                    }
                }
            ],
        ];
    }
}
