<?php

namespace App\Http\Requests\Company\Banking\Banks;

use Illuminate\Foundation\Http\FormRequest;

class CreateBankConnectionRequest extends FormRequest
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
            "bank_id" => 'required|integer|exists:banks,id',
            "userID" => 'required|string|max:225',
            "password" => 'required|string|max:225',
            "otp" => 'required|string|max:10',
        ];
    }
}
