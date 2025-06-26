<?php

namespace App\Exports\Banking;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionExport implements FromCollection, WithHeadings
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function collection()
    {
        return $this->records->map(function ($record) {
            return [
                'Transaction ID' => $record->transactionID,
                'Reference ID' => $record->referenceID,
                'Description' => $record->description,
                'Amount' => $record->amount,
                'Type' => $record->type,
                'Category' => $record->category,
                'Payment Mode' => $record->mode_of_payment,
                'Bank' => optional($record->account)->name,
                'Date' => $record->transaction_date,
                'Created At' => $record->created_at,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Reference ID',
            'Description',
            'Amount',
            'Type',
            'Category',
            'Payment Mode',
            'Bank',
            'Date',
            'Created At',
        ];
    }
}
