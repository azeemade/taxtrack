<?php

namespace App\Exports\Banking;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReconciliationSummaryExport implements FromArray, WithHeadings
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn($r) => [
            'Date'             => $r['date'],
            'Batch ID'         => $r['batch_id'] ?? '',
            'Discrepancies'    => $r['discrepancies'],
            'Dual Reflections' => $r['dual_reflections'],
            'No Discrepancies' => $r['no_discrepancies'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Date','Batch ID','Discrepancies','Dual Reflections','No Discrepancies'];
    }
}
