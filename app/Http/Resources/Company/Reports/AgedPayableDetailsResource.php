<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgedPayableDetailsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vendorTotalAmountDue = $this->invoices->reduce(function ($carry, $item) {
            return $carry + ($item?->paymentRecords()->latest()->amount_due ?? $item->purchase_invoices_total);
        }, 0) ?? 0.00;

        return [
            'vendor_name' => $this->vendor_name,
            'invoices' => $this->invoices->map(function ($invoice) {
                $amountDue = $invoice->paymentRecords()->latest()->amount_due ?? $invoice->purchase_invoices_total;
                return [
                    'date' => $invoice->purchase_order_due_date,
                    'reference' => $invoice->purchase_invoiceID,
                    'less_1m' => $amountDue < 1000000 ? $amountDue : null,
                    '1m_2m' => $amountDue >= 1000000 && $amountDue < 1000000 ? $amountDue : null,
                    '2m_3m' => $amountDue >= 2000000 && $amountDue < 3000000 ? $amountDue : null,
                    '3m_more'  => $amountDue > 3000000 ? $amountDue : null,
                    'total' => $amountDue
                ];
            }),
            'total' => [
                'less_1m' => $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                '1m_2m' => $vendorTotalAmountDue >= 1000000 && $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                '2m_3m' => $vendorTotalAmountDue >= 2000000 && $vendorTotalAmountDue < 3000000 ? $vendorTotalAmountDue : null,
                '3m_more'  => $vendorTotalAmountDue > 3000000 ? $vendorTotalAmountDue : null,
                'total' => $vendorTotalAmountDue
            ]
        ];
    }
}
