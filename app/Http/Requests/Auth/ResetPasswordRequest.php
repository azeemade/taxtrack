<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'email' => 'sometimes|required|email|exists:users,email',
            'token' => 'sometimes|required|string|exists:verification_tokens,token',
            'current_password' => ['sometimes', 'required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current Password is required',
            'password.required' => 'Password is required',
            'password.confirmed' => 'Password does not match',
            'token.required' => 'Token is required',
            'email.required' => 'Email is required',
            // 'password_confirmation.required' => 'Password confirmation is required',
        ];
    }
}
