<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
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
            'companies.*.registration_id' => 'nullable|string||max:20',
            'companies.*.fiscal_year_start' => 'nullable|date_format:m-d',
            'companies.*.fiscal_year_end' => 'nullable|date_format:m-d',
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
                        if ($userCheck) {
                            $client = Client::whereIn('company_id', $user['company_id'])
                                ->where('user_id', $userCheck['id'])
                                ->first();
                            if ($client) {
                                $fail('User with ' . $value . ' already exist in this company.');
                            }
                        }
                    }
                }
            ]
        ];
    }
}
