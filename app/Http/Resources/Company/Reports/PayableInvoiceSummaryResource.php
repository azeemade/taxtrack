<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayableInvoiceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_date' => $this->created_at,
            'vendor_name' => $this->vendor->vendor_name,
            'source' => 'Payable invoice',
            'reference' => $this->purchase_invoiceID,
            'planned_date' => $this->invoice_end_date,
            'gross' => $this->purchase_invoices_total,
            'balance' => $this->paymentRecords()->latest()->first()->amount_due ?? $this->purchase_invoices_total,
            'status' => $this->status
        ];
    }
}
