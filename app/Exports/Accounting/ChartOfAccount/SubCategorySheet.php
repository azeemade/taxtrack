<?php

namespace App\Exports\Accounting\ChartOfAccount;

use App\Models\FinanceAccountSubCategory;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubCategorySheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return FinanceAccountSubCategory::select('name')->get();
    }

    public function headings(): array
    {
        return ['SubCategory Name'];
    }
}
