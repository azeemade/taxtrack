<?php

namespace App\Exports\Banking;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Exports\Sheets\SimpleArraySheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BankReconciliationStructuredExport implements WithMultipleSheets
{
    public function __construct(private array $report) {}

    public function sheets(): array
    {
        return [
            new SimpleArraySheet('App', $this->rowsForSide($this->report['app']['grouped'], true, $this->report['app']['opening_balance'] ?? null)),
            new SimpleArraySheet('Bank statement', $this->rowsForSide($this->report['bank']['grouped'], false, null)),
        ];
    }

    private function rowsForSide($grouped, bool $isApp, $opening)
    {
        $rows = [['Date','Reference','Amount','Balance']];
        if ($isApp && $opening !== null) {
            $rows[] = ['(Opening)', '', '', $opening];
            $rows[] = []; // spacer
        }
        foreach ($grouped as $group) {
            $rows[] = [$group['date'], '', '', '']; // date header row
            foreach ($group['items'] as $tx) {
                $rows[] = [$tx['date'], $tx['reference'], $tx['amount'], $tx['balance']];
            }
            $rows[] = []; // spacer between dates
        }
        return $rows;
    }
}