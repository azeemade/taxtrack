<?php

namespace App\Exports\Banking;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BankStatementTemplateExport implements FromArray, WithHeadings
{
    protected array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Define the header row
     */
    public function headings(): array
    {
        return [
            'transaction_date',
            'reference_id',
            'description',
            'withdrawals',
            'lodgments',
            'balance',
            'value_date',
        ];
    }

    /**
     * Define sample/example rows (or use $this->data if passed)
     */
    public function array(): array
    {
        if (!empty($this->data)) {
            return $this->data;
        }

        // Default template with 3 example rows
        return [
            ['01/01/2025', 'REF12345', 'ATM Withdrawal', 5000, null, 45000.00, '01/01/2025'],
            ['03/01/2025', 'REF12346', 'Salary Credit', null, 120000, 165000.00, '03/01/2025'],
            ['05/01/2025', 'REF12347', 'POS Purchase', 25000, null, 140000.00, '05/01/2025'],
        ];
    }
}
