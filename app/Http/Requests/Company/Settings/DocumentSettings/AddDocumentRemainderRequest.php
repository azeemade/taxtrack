<?php

namespace App\Http\Requests\Company\Settings\DocumentSettings;

use Illuminate\Foundation\Http\FormRequest;

class AddDocumentRemainderRequest extends FormRequest
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
            'invoice_due_type' => 'required|string|in:due-in,overdue-by',
            'invoice_due_days' => 'required|integer',
            'email_remainder_title' => 'required|string|max:50',
            'email_content' => 'required|string',
            'include_pdf_copy_of_invoice' => 'nullable|boolean',
            'email_cc' => 'nullable|string',
            'additional_attachment' => 'nullable|string',
        ];
    }
}
