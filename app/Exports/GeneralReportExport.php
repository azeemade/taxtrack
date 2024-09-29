<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GeneralReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $records;
    protected $recordHeadings;

    public function __construct($records, $recordHeadings)
    {
        $this->records = $records;
        $this->recordHeadings = $recordHeadings;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $records = $this->records;
    }

    public function map($records): array
    {
        return $records;
    }

    public function headings(): array
    {
        return $this->recordHeadings;
    }
}
