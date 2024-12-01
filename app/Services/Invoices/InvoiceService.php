<?php

namespace App\Services\Invoices;

use App\Enums\DocumentableTypeEnums;
use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\GeneralEnums;
use App\Enums\ShareStatusEnums;
use App\Helpers\GeneralHelper;
use App\Models\Invoice;

class InvoiceService
{
    public function create($request)
    {
        $record = Invoice::create([
            ...$request,
            'quote_date' => $request['quote_date'] ?? now(),
            'invoiceID' => $this->generateInvoiceId(),
            'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : ($request['save_status'] == FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value ? FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value : GeneralEnums::PENDING->value)
        ]);

        foreach ($request['line_items'] as $value) {
            $record->lineItems()->create([
                'documentable_type' => DocumentableTypeEnums::INVOICE->value,
                'category_id' => $value['category_id'],
                'quantity' => $value['quantity'],
                'price' => $value['unit_price'],
                'discount' => $value['discount'],
                'vat' => $value['vat'],
                'amount' => $value['line_item_total']
            ]);
        }

        if ($request['save_status'] == 'send') {
        }

        if ($request['save_status'] == FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value) {
        }

        return $record;
    }


    public function calculateLineItemTotalUnitPrice(float $unit, int $quantity)
    {
        $lineItemTotalUnitPrice = $unit * $quantity;
        return round($lineItemTotalUnitPrice, 4);
    }

    public function calculateLineItemTotal(float $total_unit_price, float $discount, float $vat)
    {
        $lineItemTotal = ($total_unit_price - (($total_unit_price * $discount) / 100))  * (1 + $vat / 100);
        return round($lineItemTotal, 4);
    }

    public function calculateQuoteSubTotal(array $line_items)
    {
        $quoteSubTotal = 0.00;
        foreach ($line_items as $line_item) {
            $quoteSubTotal += $this->calculateLineItemTotal($line_item['total_unit_price'], $line_item['discount'], $line_item['vat']);
        }
        return round($quoteSubTotal, 4);
    }

    public function calculateQuoteTotal(float $sub_total, float $shipping_charge, float $additional_charge)
    {
        $quoteTotal = $sub_total + $shipping_charge + $additional_charge;
        return round($quoteTotal, 4);
    }

    protected function generateInvoiceId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Invoice',
            "modelField" => 'invoiceID',
            "prefix" => 'inv-',
            "idLength" => 4,
        ]);
    }
}
