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
            'currency_id' => ['required', 'integer', 'exists:currencies,id', function ($attribute, $value, $fail) {
                $customerCurrency = Customer::where('currency_id', $value)
                    ->where('id', $this->input('customer_id'))
                    ->first();

                if (!$customerCurrency) {
                    $fail('Invalid customer currency selected');
                }
            }],
            'start_date' => 'required|string|date:Y-m-d',
            'due_date' => 'required|string|date:Y-m-d|after_or_equal:start_date',
            'additional_referenceID' => 'nullable|string|max:20',
            'terms_and_conditions' => 'nullable|string|max:250',
            'customer_note' => 'nullable|string|max:250',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => ['required', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $calculatedSubTotal = $this->invoiceService->calculateQuoteSubTotal($this->input('line_items'));
                if ($calculatedSubTotal != round($value, 4)) {
                    $fail('Sub total does not match the total of line items');
                }
            }],
            'invoice_value' => ['required', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $calculatedTotal = $this->invoiceService->calculateQuoteTotal($this->input('sub_total'), $this->input('shipping_charge'), $this->input('additional_charge'));
                if ($calculatedTotal != round($value, 4)) {
                    $fail('Invoice total does not match the provided');
                }
            }],
            'recurring_start_date' => 'nullable|required_if:save_status,recur|string|date:Y-m-d',
            'recurring_end_date' => 'nullable|required_if:save_status,recur|string|date:Y-m-d',
            'repeat' => 'nullable|required_if:save_status,recur|integer',
            'repeat_period' => 'nullable|required_if:save_status,recur|string|in:month,day,week,year',
            'save_status' => 'required|string|in:draft,save,send,recur',
            'line_items' => 'nullable|array',
            'line_items.*.name' => 'required|string|max:50',
            'line_items.*.category_id' => 'nullable|integer|exists:categories,id',
            'line_items.*.quantity' => 'nullable|integer|min:0',
            'line_items.*.unit_price' => 'nullable|numeric|min:0.00',
            'line_items.*.total_unit_price' => ['nullable', 'numeric', 'min:0.00', function ($attribute, $value, $fail) {
                $index = explode('.', $attribute)[1];
                $item = $this->input("line_items.$index");

                $calculatedLineItemUnitPrice = $this->invoiceService->calculateLineItemTotalUnitPrice($item['unit_price'], $item['quantity']);
                if ($calculatedLineItemUnitPrice != round($value, 4)) {
                    $fail('Total unit price does not match the provided');
                }
            }],
            'line_items.*.discount' => 'nullable|numeric|min:0',
            'line_items.*.vat' => 'nullable|numeric|min:0',
            'line_items.*.line_total' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                $index = explode('.', $attribute)[1];
                $item = $this->input("line_items.$index");

                $calculatedLineItemUnitPrice = $this->invoiceService->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
                if ($calculatedLineItemUnitPrice != round($value, 4)) {
                    $fail('Line total does not match the provided');
                }
            }],
        ];
    }
}
