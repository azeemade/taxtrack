<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceivableInvoiceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invoice_number' => $this->invoiceID,
            'contact' => $this->customer->company_name,
            'invoice_date' => $this->created_at,
            'source' => 'Payable invoice',
            'expected' => $this->due_date,
            'reference' => $this->additional_referenceID,
            'gross' => $this->invoice_value,
            'balance' => $this->paymentRecords()->latest()->first()->amount_due ?? $this->purchase_invoices_total,
            'status' => $this->status,
            'sent_status' => $this->share_status
        ];
    }
}
