<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class SharedReminderRequest extends FormRequest
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
            'optional_cc' => 'nullable|email|string',
            'email_copy' => 'nullable',
            'subject' => 'required|string|max:50',
            'body' => 'required|string|max:500',
            'main_file' => 'nullable|string',
            'additional_attachments' => 'nullable|string',
        ];
    }
}
