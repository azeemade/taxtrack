<?php

namespace App\Http\Requests\Company\Sales\Quotes;

use App\Models\Customer;
use App\Services\Quotes\QuoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
            'customer_id' => 'required|integer|exists:customers,id,company_id,' . Auth::user()->current_company_id,
            // 'currency_id' => [
            //     'required',
            //     'integer',
            //     'exists:currencies,id',
            //     $this->validateCustomerCurrency()
            // ],
            'quote_date' => 'nullable|string|date:Y-m-d',
            'additional_referenceID' => 'nullable|string|max:20',
            'terms_and_conditions' => 'nullable|string|max:250',
            'customer_note' => 'nullable|string|max:250',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateSubTotal()
            ],
            'quote_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateQuoteTotal()
            ],
            'save_status' => 'required|string|in:draft,save,send',
            'line_items' => 'required|array',
            'line_items.*.id' => 'sometimes|integer|exists:line_items,id',
            'line_items.*.item_details' => 'required|string|max:50',
            'line_items.*.category_id' => 'required',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.price' => 'required|numeric|min:0.00',
            'line_items.*.total_unit_price' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateLineItemsUnitPrice()
            ],
            'line_items.*.discount' => 'required|numeric|min:0',
            'line_items.*.vat' => 'required|numeric|min:0',
            'line_items.*.amount' => [
                'required',
                'numeric',
                'min:0',
                $this->validateLineItemsLineTotal()
            ],
        ];
    }

    public function messages(): array
    {
        return [
            "line_items.*.quantity.required" => 'Quantity is required',
            "line_items.*.quantity.numeric" => 'integer is required'
        ];
    }

    /**
     * Validate the customer's currency.
     */
    // private function validateCustomerCurrency(): \Closure
    // {
    //     return function ($attribute, $value, $fail) {
    //         $customerCurrency = Customer::where('currency_id', $value)
    //             ->where('id', $this->input('customer_id'))
    //             ->exists();

    //         if (!$customerCurrency) {
    //             $fail('Invalid customer currency selected');
    //         }
    //     };
    // }

    /**
     * Validate the customer's sub total.
     */
    private function validateSubTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedSubTotal = $this->quoteService->calculateQuoteSubTotal($this->input('line_items'));
            if ($calculatedSubTotal != round($value, 4)) {
                $fail('Sub total does not match the total of line items');
            }
        };
    }

    /**
     * Validate the customer's total.
     */
    private function validateQuoteTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedTotal = $this->quoteService->calculateQuoteTotal($this->input('sub_total'), $this->input('shipping_charge'), $this->input('additional_charge'));
            if ($calculatedTotal != round($value, 4)) {
                $fail('Quote total does not match the provided');
            }
        };
    }

    /**
     * Validate the customer's line items unit price.
     */
    private function validateLineItemsUnitPrice(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->quoteService->calculateLineItemTotalUnitPrice($item['price'], $item['quantity']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Total unit price does not match the provided');
            }
        };
    }

    /**
     * Validate the customer's line items unit price.
     */
    private function validateLineItemsLineTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->quoteService->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Line total does not match the provided');
            }
        };
    }
}
