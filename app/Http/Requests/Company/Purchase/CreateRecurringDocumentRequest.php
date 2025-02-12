<?php

namespace App\Http\Requests\Company\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class CreateRecurringDocumentRequest extends FormRequest
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
            'recurring_start_date' => 'required|date_format:Y-m-d',
            'recurring_end_date' => 'required|date_format:Y-m-d|after_or_equal:recurring_start_date',
            'repeat' => 'required|integer',
            'repeat_period' => 'required|string|in:month,day,week,year',
        ];
    }
}
