<?php

namespace App\Http\Requests\Auth;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class CreateOnboardingRoleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $role = Role::where('name', $value)
                        ->whereRelation('companies', 'company_id', $this->company_id)
                        ->first();
                    if ($role) {
                        $fail('This name already exist');
                    }
                }
            ],
            'company_id' => 'required|exists:companies,id'
        ];
    }
}
