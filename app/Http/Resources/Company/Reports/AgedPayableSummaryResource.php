<?php

namespace App\Http\Resources\Company\Reports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgedPayableSummaryResource extends JsonResource
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
            'breakdowns' => [
                'less_1m' => $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                '1m_2m' => $vendorTotalAmountDue >= 1000000 && $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                '2m_3m' => $vendorTotalAmountDue >= 2000000 && $vendorTotalAmountDue < 3000000 ? $vendorTotalAmountDue : null,
                '3m_more'  => $vendorTotalAmountDue > 3000000 ? $vendorTotalAmountDue : null,
                'total' => $vendorTotalAmountDue
            ]
        ];
    }
}
