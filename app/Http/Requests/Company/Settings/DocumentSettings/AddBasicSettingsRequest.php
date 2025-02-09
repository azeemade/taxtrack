<?php

namespace App\Http\Requests\Company\Settings\DocumentSettings;

use Illuminate\Foundation\Http\FormRequest;

class AddBasicSettingsRequest extends FormRequest
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
            'typography_setting_body_font_family' => 'sometimes|nullable|string|max:50',
            'typography_setting_body_font_size' => 'sometimes|nullable|integer|min:9|max:20',
            'typography_setting_heading_font_family' => 'sometimes|nullable|string|max:50',
            'typography_setting_heading_font_size' => 'sometimes|nullable|integer|min:18|max:50',
            'title_setting_draft_invoice' => 'sometimes|nullable|string|max:50',
            'title_setting_approved_invoice' => 'sometimes|nullable|string|max:50',
            'title_setting_overdue_invoice' => 'sometimes|nullable|string|max:50',
            'title_setting_credit_note' => 'sometimes|nullable|string|max:50',
            'title_setting_draft_purchase_order' => 'sometimes|nullable|string|max:50',
            'title_setting_purchase_order' => 'sometimes|nullable|string|max:50',
            'title_setting_draft_quote' => 'sometimes|nullable|string|max:50',
            'title_setting_quote' => 'sometimes|nullable|string|max:50',
            'title_setting_draft_bills' => 'sometimes|nullable|string|max:50',
            'title_setting_bills' => 'sometimes|nullable|string|max:50',
            'title_setting_receipt' => 'sometimes|nullable|string|max:50',

            'content_display.show_tax_number' => 'sometimes|required|boolean',
            'content_display.show_column_heading' => 'sometimes|required|boolean',
            'content_display.show_item_id' => 'sometimes|required|boolean',
            'content_display.show_unit_price_and_quantity' => 'sometimes|required|boolean',
            'content_display.show_tax_column' => 'sometimes|required|boolean',
            'content_display.show_registered_address' => 'sometimes|required|boolean',
            'content_display.show_logo' => 'sometimes|required|boolean',
            'content_display.show_discount' => 'sometimes|required|boolean',
            'content_display.show_contact_account_number' => 'sometimes|required|boolean',

            'document_contact_address' => 'sometimes|required|string|max:100',

            'payment_term_for_invoice_and_bills.show_terms' => 'sometimes|nullable|boolean',
            'payment_term_for_invoice_and_bills.terms' => 'sometimes|nullable|string|max:100',
            'payment_term_for_quote.show_terms' => 'sometimes|nullable|boolean',
            'payment_term_for_quote.terms' => 'sometimes|nullable|string|max:100',
            'other_setting_primary_color' => 'sometimes|nullable|string|max:6',
            'other_setting_secondary_color' => 'sometimes|nullable|string|max:6',
            'other_setting_design_template' => 'sometimes|nullable|string|in:basic,classic,premium',
        ];
    }
}
