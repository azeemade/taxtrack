<?php

namespace App\Exports\Report;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BankReconciliationLinesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $lines;

    public function __construct($lines)
    {
        $this->lines = collect($lines); // ensure collection
    }

    /**
     * Return the collection to export
     */
    public function collection()
    {
        return $this->lines;
    }

    /**
     * Define headings for the export
     */
    public function headings(): array
    {
        return [
            'Date',
            'Reference',
            'Bank Amount',
            'App Amount',
            'Bank Type',
            'App Type',
            'Status',
            'Context'
        ];
    }

    /**
     * Map each record to row format
     */
    public function map($line): array
    {
        return [
            $line['date'] ?? '',
            $line['reference'] ?? '',
            $line['bank_amount'] !== null ? number_format($line['bank_amount'], 2) : '',
            $line['app_amount'] !== null ? number_format($line['app_amount'], 2) : '',
            $line['bank_type'] ?? '',
            $line['app_type'] ?? '',
            ucfirst($line['status']),   // e.g. Matched / Mismatch / Unmatched
            $line['context'] ?? ''
        ];
    }
}