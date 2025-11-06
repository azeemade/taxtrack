<?php

namespace App\Http\Requests\Company\Purchase\PurchaseOrder;

use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'line_items.*.category_id' => 'nullable',
            'line_items.*.account_id' => 'required|present|integer|exists:finance_chart_of_accounts,id',
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
                $fail('The sub total (' . $value . ') does not match the calculated total of line items (' . $calculatedSubTotal . ')');
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
                $fail('The purchase order total (' . $value . ') does not match the calculated total (' . $calculatedTotal . ')');
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
                $fail('The total unit price for item ' . ($index + 1) . ' does not match quantity × unit price');
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
                $fail('The amount for item ' . ($index + 1) . ' does not match the calculated total');
            }
        };
    }

    /**
     * Get custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Vendor related messages
            'vendor_id.required' => 'Please select a vendor.',
            'vendor_id.integer' => 'Vendor must be a valid ID.',
            'vendor_id.exists' => 'The selected vendor does not exist.',

            // Date related messages
            'purchase_order_date.required' => 'Purchase order date is required.',
            'purchase_order_date.date_format' => 'Purchase order date must be in YYYY-MM-DD format.',

            // Purchase order number
            'purchase_order_no.string' => 'Purchase order number must be a string.',
            'purchase_order_no.max' => 'Purchase order number cannot exceed 10 characters.',

            // Terms and comments
            'terms_and_conditions.string' => 'Terms and conditions must be text.',
            'terms_and_conditions.max' => 'Terms and conditions cannot exceed 250 characters.',
            'additional_comment.string' => 'Additional comment must be text.',
            'additional_comment.max' => 'Additional comment cannot exceed 250 characters.',

            // Numeric fields
            'vat.numeric' => 'VAT must be a number.',
            'vat.min' => 'VAT cannot be negative.',
            'discount.numeric' => 'Discount must be a number.',
            'discount.min' => 'Discount cannot be negative.',
            'shipping_charge.numeric' => 'Shipping charge must be a number.',
            'shipping_charge.min' => 'Shipping charge cannot be negative.',
            'additional_charge.numeric' => 'Additional charge must be a number.',
            'additional_charge.min' => 'Additional charge cannot be negative.',
            'sub_total.required' => 'Sub total is required.',
            'sub_total.numeric' => 'Sub total must be a number.',
            'sub_total.min' => 'Sub total cannot be negative.',
            'purchase_order_value.required' => 'Purchase order value is required.',
            'purchase_order_value.numeric' => 'Purchase order value must be a number.',
            'purchase_order_value.min' => 'Purchase order value cannot be negative.',

            // Save status
            'save_status.required' => 'Save status is required.',
            'save_status.string' => 'Save status must be a string.',
            'save_status.in' => 'Save status must be either draft, save, or send.',

            // Line items array
            'line_items.required' => 'At least one line item is required.',
            'line_items.array' => 'Line items must be provided as an array.',

            // Line item fields
            'line_items.*.id.integer' => 'Line item ID must be a number.',
            'line_items.*.id.exists' => 'The selected line item does not exist.',
            'line_items.*.item_details.required' => 'Item description is required for all line items.',
            'line_items.*.item_details.string' => 'Item description must be text.',
            'line_items.*.item_details.max' => 'Item description cannot exceed 50 characters.',
            'line_items.*.category_id.integer' => 'Category ID must be a number.',
            'line_items.*.category_id.exists' => 'The selected category does not exist.',
            'line_items.*.account_id.required' => 'Account is required for all line items.',
            'line_items.*.account_id.integer' => 'Account must be a valid ID.',
            'line_items.*.account_id.exists' => 'The selected account does not exist.',
            'line_items.*.quantity.required' => 'Quantity is required for all line items.',
            'line_items.*.quantity.integer' => 'Quantity must be a whole number.',
            'line_items.*.quantity.min' => 'Quantity must be at least 1.',
            'line_items.*.price.required' => 'Price is required for all line items.',
            'line_items.*.price.numeric' => 'Price must be a number.',
            'line_items.*.price.min' => 'Price cannot be negative.',
            'line_items.*.total_unit_price.required' => 'Total unit price is required for all line items.',
            'line_items.*.total_unit_price.numeric' => 'Total unit price must be a number.',
            'line_items.*.total_unit_price.min' => 'Total unit price cannot be negative.',
            'line_items.*.discount.numeric' => 'Discount must be a number.',
            'line_items.*.discount.min' => 'Discount cannot be negative.',
            'line_items.*.vat.numeric' => 'VAT must be a number.',
            'line_items.*.vat.min' => 'VAT cannot be negative.',
            'line_items.*.amount.required' => 'Amount is required for all line items.',
            'line_items.*.amount.numeric' => 'Amount must be a number.',
            'line_items.*.amount.min' => 'Amount cannot be negative.',

            // Custom validation messages
            'sub_total' => 'Sub total does not match the total of line items.',
            'purchase_order_value' => 'Purchase order total does not match the calculated total.',
            'line_items.*.total_unit_price' => 'Total unit price does not match quantity × price.',
            'line_items.*.amount' => 'Line item amount does not match the calculated total.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 422)
        );
    }
}
