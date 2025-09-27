<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableInvoiceDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vendor_name' => $this->vendor_name,
            'invoices' => $this->invoices->map(function ($invoice) {
                return [
                    'date' => $invoice->purchase_order_due_date,
                    'source' => 'Payable invoice',
                    'reference' => $invoice->purchase_invoiceID,
                    'line_items' => $invoice->lineItems->map(function ($item) {
                        return [
                            "code" => null,
                            "description" => $item->item_details,
                            "quantity" => $item->quantity,
                            "price" => $item->price,
                            "total" => $item->amount,
                        ];
                    }),
                    'line_item_gross' => $invoice->lineItems->reduce(function ($carry, $item) {
                        return $carry + ($item->amount);
                    }, 0) ?? 0.00,
                    'invoice_total' => $invoice->purchase_invoices_total,
                    'status' => $invoice->status
                ];
            })
        ];
    }
}
