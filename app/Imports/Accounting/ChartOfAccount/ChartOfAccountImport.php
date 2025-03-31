<?php

namespace Imports\Accounting\ChartOfAccount;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class ChartOfAccountImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        return $rows; // Data will be handled in the service class
    }
}