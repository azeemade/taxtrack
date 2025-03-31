<?php

namespace App\Exports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ChartOfAccountDownloadTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['Account Name Example', 'Type Example', 'Category Example', 'Sub-Category Example', 'Description Example', 'Reference Code Example', 'Opening Balance Example'],
        ];
    }

    public function headings(): array
    {
        return ['Account Name', 'Account Type', 'Account Category', 'Sub-Category', 'Description', 'Reference Code', 'Opening Balance'];
    }
}