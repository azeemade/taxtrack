<?php

namespace App\Http\Requests\Company\Sales\Invoices;

use App\Models\Customer;
use App\Services\Invoices\InvoiceService;
use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceRequest extends FormRequest
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
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
            // 'currency_id' => [
            //     'required',
            //     'integer',
            //     'exists:currencies,id',
            //     $this->validateCustomerCurrency()
            // ],
            'start_date' => 'required|date_format:Y-m-d',
            'due_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'additional_referenceID' => 'nullable|string|max:20',
            'terms_and_conditions' => 'nullable|string|max:250',
            'customer_note' => 'nullable|string|max:250',
            'quote_id' => 'nullable|integer|exists:quotes,id',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateSubTotal()
            ],
            'invoice_value' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateInvoiceValue()
            ],
            'recurring_start_date' => 'nullable|required_if:save_status,recur|date_format:Y-m-d',
            'recurring_end_date' => 'nullable|required_if:save_status,recur|date_format:Y-m-d',
            'repeat' => 'nullable|required_if:save_status,recur|integer',
            'repeat_period' => 'nullable|required_if:save_status,recur|string|in:month,day,week,year',
            'save_status' => 'required|string|in:draft,save,send,recur',
            'line_items' => 'required|array',
            'line_items.*.id' => 'sometimes|integer|exists:line_items,id',
            'line_items.*.item_details' => 'required|string|max:50',
            'line_items.*.category_id' => 'nullable|integer|exists:categories,id',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.price' => 'required|numeric|min:0.00',
            'line_items.*.total_unit_price' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateTotalUnitPrice()
            ],
            'line_items.*.discount' => 'nullable|numeric|min:0',
            'line_items.*.vat' => 'nullable|numeric|min:0',
            'line_items.*.amount' => [
                'required',
                'numeric',
                'min:0',
                $this->validateLineItemAmount()
            ],
        ];
    }

    // private function validateCustomerCurrency(): \Closure
    // {
    //     return function ($attribute, $value, $fail) {
    //         $customerCurrency = Customer::where('currency_id', $value)
    //             ->where('id', $this->input('customer_id'))
    //             ->first();

    //         if (!$customerCurrency) {
    //             $fail('Invalid customer currency selected');
    //         }
    //     };
    // }

    private function validateSubTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedSubTotal = $this->invoiceService->calculateQuoteSubTotal($this->input('line_items'));
            if ($calculatedSubTotal != round($value, 4)) {
                $fail('Sub total does not match the total of line items');
            }
        };
    }

    private function validateInvoiceValue(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedTotal = $this->invoiceService->calculateQuoteTotal(
                $this->input('sub_total'),
                $this->input('shipping_charge'),
                $this->input('additional_charge')
            );
            if ($calculatedTotal != round($value, 4)) {
                $fail('Invoice total does not match the provided');
            }
        };
    }

    private function validateTotalUnitPrice(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->invoiceService->calculateLineItemTotalUnitPrice($item['price'], $item['quantity']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Total unit price does not match the provided');
            }
        };
    }

    private function validateLineItemAmount(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->invoiceService->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Line total does not match the provided');
            }
        };
    }
}
