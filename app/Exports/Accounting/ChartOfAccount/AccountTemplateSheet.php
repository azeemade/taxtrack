<?php

namespace App\Exports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountTemplateSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return collect([
            [
                'account_sub_category_name' => "Accounts Payable",
                'name' => 'SayLita',
                'description' => 'Salita Account'
            ]
        ]);
    }

    public function headings(): array
    {
        return ['Account SubCategory Name', 'Name', 'Description'];
    }
}
