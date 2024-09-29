<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class AttachUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone_number' => 'required|string|max:255|unique:users,phone_number',
            'password' => 'nullable|string|min:6',
            'company_id' => 'nullable|integer|exists:companies,id',
            'contact_person' => 'required|int',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.max' => 'Name cannot be more than 255 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Email address is invalid',
            'email.unique' => 'Email address already exists',
            'phone_number.required' => 'Phone number is required',
            'phone_number.unique' => 'Phone number already exists',
            'password.min' => 'Password must be at least 6 characters',
            'company_id.exists' => 'Company not found',
            'contact_person.required' => 'Contact person status is required',
        ];
    }
}
