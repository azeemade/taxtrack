<?php

namespace App\Http\Requests\Auth;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class InviteUsersRequest extends FormRequest
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
            'users' => 'nullable|array',
            'users.*.name' => 'required|string',
            'users.*.roles' => 'nullable|array',
            'users.*.roles.*' => 'required|string|max:50',
            'users.*.company' => 'nullable|array',
            'users.*.company.*' => 'nullable|integer|exists:companies,id',
            'users.*.email' => [
                'required',
                'string',
                'email',
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
