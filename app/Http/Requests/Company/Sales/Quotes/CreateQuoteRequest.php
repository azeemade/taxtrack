<?php

namespace App\Http\Requests\Company\Sales\Quotes;

use App\Models\Customer;
use App\Services\Quotes\QuoteService;
use Illuminate\Foundation\Http\FormRequest;

class CreateQuoteRequest extends FormRequest
{
    protected QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
    }

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
            'customer_id' => 'required|integer|exists:customers,id',
            'currency_id' => ['required', 'integer', 'exists:currencies,id', function ($attribute, $value, $fail) {
                $customerCurrency = Customer::where('currency_id', $value)
                    ->where('id', $this->input('customer_id'))
                    ->first();
                    
                if (!$customerCurrency) {
                    $fail('Invalid customer currency selected');
                }
            }],
            'quote_date' => 'nullable|string|date:Y-m-d',
            'additional_referenceID' => 'nullable|string|max:20',
            'terms_and_conditions' => 'nullable|string|max:250',
            'customer_note' => 'nullable|string|max:250',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => ['required', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $calculatedSubTotal = $this->quoteService->calculateQuoteSubTotal($this->input('line_items'));
                if ($calculatedSubTotal != round($value, 4)) {
                    $fail('Sub total does not match the total of line items');
                }
            }],
            'quote_total' => ['required', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $calculatedTotal = $this->quoteService->calculateQuoteTotal($this->input('sub_total'), $this->input('shipping_charge'), $this->input('additional_charge'));
                if ($calculatedTotal != round($value, 4)) {
                    $fail('Quote total does not match the provided');
                }
            }],
            'save_status' => 'required|string|in:draft,save,send',
            'line_items' => 'nullable|array',
            'line_items.*.name' => 'required|string|max:50',
            'line_items.*.category_id' => 'nullable|integer|exists:categories,id',
            'line_items.*.quantity' => 'nullable|integer|min:0',
            'line_items.*.unit_price' => 'nullable|numeric|min:0.00',
            'line_items.*.total_unit_price' => ['nullable', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $index = explode('.', $attribute)[1];
                $item = $this->input("line_items.$index");

                $calculatedLineItemUnitPrice = $this->quoteService->calculateLineItemTotalUnitPrice($item['unit_price'], $item['quantity']);
                if ($calculatedLineItemUnitPrice != round($value, 4)) {
                    $fail('Total unit price does not match the provided');
                }
            }],
            'line_items.*.discount' => 'nullable|numeric|min:0',
            'line_items.*.vat' => 'nullable|numeric|min:0',
            'line_items.*.line_total' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                $index = explode('.', $attribute)[1];
                $item = $this->input("line_items.$index");

                $calculatedLineItemUnitPrice = $this->quoteService->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
                if ($calculatedLineItemUnitPrice != round($value, 4)) {
                    $fail('Line total does not match the provided');
                }
            }],
        ];
    }
}
