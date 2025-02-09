<?php

namespace App\Http\Requests\Company\Purchase\PurchaseOrder;

use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        parent::__construct();
        $this->purchaseOrderService = $purchaseOrderService;
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
            'vendor_id' => 'required|integer|exists:vendors,id',
            'invoice_id' => 'nullable|integer|exists:purchase_invoices,id',
            'purchase_order_date' => 'required|date_format:Y-m-d',
            'purchase_order_no' => 'sometimes|string|max:10',
            'terms_and_conditions' => 'nullable|string|max:250',
            'additional_comment' => 'nullable|string|max:250',
            'vat' => 'nullable|numeric|min:0.00',
            'discount' => 'nullable|numeric|min:0.00',
            'shipping_charge' => 'nullable|numeric|min:0.00',
            'additional_charge' => 'nullable|numeric|min:0.00',
            'sub_total' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validateSubTotal()
            ],
            'purchase_order_value' => [
                'required',
                'numeric',
                'min:0.00',
                $this->validatePurchaseOrderValue()
            ],
            'save_status' => 'required|string|in:draft,save,send',
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

    private function validateSubTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedSubTotal = $this->purchaseOrderService->calculateSubTotal($this->input('line_items'));
            if ($calculatedSubTotal != round($value, 4)) {
                $fail('Sub total does not match the total of line items');
            }
        };
    }

    private function validatePurchaseOrderValue(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedTotal = $this->purchaseOrderService->calculateTotal(
                $this->input('sub_total'),
                $this->input('shipping_charge'),
                $this->input('additional_charge')
            );
            if ($calculatedTotal != round($value, 4)) {
                $fail('PurchaseOrder total does not match the provided');
            }
        };
    }

    private function validateTotalUnitPrice(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->purchaseOrderService->calculateLineItemTotalUnitPrice($item['price'], $item['quantity']);
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

            $calculatedLineItemUnitPrice = $this->purchaseOrderService->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Line total does not match the provided');
            }
        };
    }
}
