<?php

namespace App\Exports\Report;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BankReconciliationSummaryExport implements FromArray
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        // Format data into array for Excel, e.g., headers and rows mimicking the screenshot structure
        // Example:
        return [
            ['Date Range', $this->data['primary']['period']['start_date'] . ' to ' . $this->data['primary']['period']['end_date']],
            ['Bank Account', $this->data['account']->name],
            // Add totals summary rows...
            ['Balance in App', $this->data['primary']['balance_in_app']],
            ['Plus Outstanding Payments', $this->data['primary']['outstanding_payments']],
            // etc.
        ];
    }
}