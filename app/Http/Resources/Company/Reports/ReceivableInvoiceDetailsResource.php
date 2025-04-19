<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableInvoiceDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->start_date,
            'source' => 'Receivable invoice',
            'reference' => $this->invoiceID,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->item_details,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'discount' => $item->discount,
                    'tax' => $item->vat,
                    'gross' => $item->amount,
                ];
            }),
            'total' => $this->invoice_value,
            'status' => $this->status,
        ];
    }
}
