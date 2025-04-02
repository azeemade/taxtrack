<?php

namespace App\Exports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
// use 
class AccountTemplate implements FromCollection, WithHeadings
{
    use Exportable;
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'account_sub_category_ref_code' => 102001,
                'name' => 'SayLita',
                'description' => 'Salita Account'
            ]
        ]);
    }

    public function headings(): array
    {
        return array('Account SubCategory Reference Code', 'Name', 'Description');
    }
}
