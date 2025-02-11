<?php

namespace App\Traits;

trait DocumentValidationTrait
{
    public function validateSubTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedSubTotal = $this->calculateSubTotal($this->input('line_items'));
            if ($calculatedSubTotal != round($value, 4)) {
                $fail('Sub total does not match the total of line items');
            }
        };
    }

    public function validateDocumentTotal(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $calculatedTotal = $this->calculateTotal(
                $this->input('sub_total'),
                $this->input('shipping_charge'),
                $this->input('additional_charge')
            );
            if ($calculatedTotal != round($value, 4)) {
                $fail('Total does not match the provided');
            }
        };
    }

    public function validateTotalUnitPrice(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->calculateLineItemTotalUnitPrice($item['price'], $item['quantity']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Total unit price does not match the provided');
            }
        };
    }

    public function validateLineItemAmount(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $index = explode('.', $attribute)[1];
            $item = $this->input("line_items.$index");

            $calculatedLineItemUnitPrice = $this->calculateLineItemTotal($item['total_unit_price'], $item['discount'], $item['vat']);
            if ($calculatedLineItemUnitPrice != round($value, 4)) {
                $fail('Line total does not match the provided');
            }
        };
    }

    protected function calculateLineItemTotalUnitPrice(float $unit, int $quantity)
    {
        $lineItemTotalUnitPrice = $unit * $quantity;
        return round($lineItemTotalUnitPrice, 4);
    }

    protected function calculateLineItemTotal(float $total_unit_price, float $discount, float $vat)
    {
        $lineItemTotal = ($total_unit_price - (($total_unit_price * $discount) / 100))  * (1 + $vat / 100);
        return round($lineItemTotal, 4);
    }

    protected function calculateSubTotal(array $line_items)
    {
        $quoteSubTotal = 0.00;
        foreach ($line_items as $line_item) {
            $quoteSubTotal += $this->calculateLineItemTotal($line_item['total_unit_price'], $line_item['discount'], $line_item['vat']);
        }
        return round($quoteSubTotal, 4);
    }

    protected function calculateTotal(float $sub_total, float $shipping_charge, float $additional_charge)
    {
        $quoteTotal = $sub_total + $shipping_charge + $additional_charge;
        return round($quoteTotal, 4);
    }
}
