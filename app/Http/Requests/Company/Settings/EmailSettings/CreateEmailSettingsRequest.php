<?php

namespace App\Http\Requests\Company\Settings\EmailSettings;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmailSettingsRequest extends FormRequest
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
            'title' => 'required|string|max:25',
            'mail_copy' => 'required|string|max:255',
            'email_templates_id' => 'nullable|integer|exists:email_templates,id',
            'is_default' => 'nullable',
        ];
    }
}
