<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateBasicInformationRequest extends FormRequest
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
            'name' => 'required|string|max:250',
            'email' => [
                'required',
                'string',
                'email',
                'max:250',
                function ($attribute, $value, $fail) {
                    $user = User::where('email', $value)
                        ->first();
                    if ($user && count($user->companies) > 0) {
                        $fail('Email already exist in this company.');
                    }
                }
            ],
            'phone_number' => 'required|string',
            'country_id' => 'required|integer|exists:countries,id',
            'currency_id' => 'required|integer|exists:countries,id',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ];
    }
}
