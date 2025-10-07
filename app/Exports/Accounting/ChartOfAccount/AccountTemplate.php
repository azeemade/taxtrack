<?php

namespace App\Exports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AccountTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new AccountTemplateSheet(),  // Sheet 1
            new SubCategorySheet(),      // Sheet 2
        ];
    }
}
