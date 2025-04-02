<?php

namespace App\Exports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChartOfAccountExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $accounts;
    protected $startDate;
    protected $endDate;

    public function __construct($accounts, $startDate = null, $endDate = null)
    {
        $this->accounts = $accounts;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        return $this->accounts;
    }

    public function headings(): array
    {
        return [
            'Account Number',
            'Account Name',
            'Account Type',
            'Account Category',
            'Sub Category',
            // 'Opening Balance',
            // 'Total Debit',
            // 'Total Credit',
            'Balance',
        ];
    }

    public function map($account): array
    {
        return [
            $account->account_number,
            $account->name,
            $account->accountType->name,
            $account->accountCategory->name ?? '',
            $account->subCategory->name ?? '',
            // $account->opening_balance,
            // $account->total_debit,
            // $account->total_credit,
            $account->current_balance,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // Bold first row
        ];
    }
}
