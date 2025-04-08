<?php

namespace App\Exports\Accounting\JournalEntry;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;

class JournalEntryExport implements FromCollection, WithHeadings, WithMapping
{
    protected $entries;
    protected $index = 0;

    public function __construct($entries)
    {
        $this->entries = $entries;
    }

    public function collection()
    {
        return $this->entries;
    }

    public function map($entry): array
    {
        $this->index++; // Increment counter

        return [
            $this->index,
            \Carbon\Carbon::parse($entry['created_at'])->format('d/m/Y h:i A'),
            // \Carbon\Carbon::parse($entry['created_at'])->format('d/m/Y'),
            $entry['total_debit'],
            $entry['total_credit'],
            $entry['status'],
        ];
    }

    public function headings(): array
    {
        return [
            'S/N',
            'Date Created',
            'Total Debit',
            'Total Credit',
            'Status',
        ];
    }
}

