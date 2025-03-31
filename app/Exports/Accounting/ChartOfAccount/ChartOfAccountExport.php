<?php

namespace App\Exports\Accounting\ChartOfAccount;

use App\Models\FinanceChartOfAccount;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ChartOfAccountExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * Retrieve all Chart of Accounts.
     */
    public function collection()
    {
        return FinanceChartOfAccount::with(['subCategory', 'accountCategory', 'accountType'])->get();
    }

    /**
     * Define column headings.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Account Name',
            'Account Number',
            'Sub Category',
            'Account Category',
            'Account Type',
            'Description',
            'Opening Balance',
            'Created At'
        ];
    }

    /**
     * Map data for export.
     */
    public function map($coa): array
    {
        return [
            $coa->id,
            $coa->name,
            $coa->account_number,
            $coa->subCategory ? $coa->subCategory->name : 'N/A',
            $coa->accountCategory ? $coa->accountCategory->name : 'N/A',
            $coa->accountType ? $coa->accountType->name : 'N/A',
            $coa->description,
            $coa->opening_balance,
            $coa->created_at->format('d/m/Y')
        ];
    }
}
