<?php

namespace App\Exports\Banking;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinanceTransactionGroupExport implements FromCollection, WithHeadings
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data);
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Date',
            'Total Value',
            'Inflow Amount',
            'Outflow Amount',
            'Banks Involved',
            'Payment Type',
            'Status',
        ];
    }
}