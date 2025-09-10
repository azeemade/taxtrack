<?php

namespace App\Exports\Report;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccountSummaryExport implements FromArray, WithHeadings, WithStyles
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function array(): array
    {
        $rows = [];
        $account = $this->records['account'];
        $budget = $this->records['budget'];

        // Add account and budget metadata as header rows
        $rows[] = ['Budget Name', $budget['name']];
        $rows[] = ['Account Name', $account['account_name']];
        $rows[] = ['Account Number', $account['account_number']];
        $rows[] = []; // Empty row for spacing

        // Add table headers
        $rows[] = ['Month', 'Year', 'Actual', 'Budgeted', 'Variance', 'Variance %'];

        // Add period data
        foreach ($account['periods'] as $period) {
            $rows[] = [
                ucfirst($period['month']),
                $period['year'],
                $period['actual'],
                $period['budgeted'],
                $period['variance'],
                $period['variance_percentage'] . '%',
            ];
        }

        // Add totals row
        $rows[] = [
            'Total',
            '',
            $account['total']['actual'],
            $account['total']['budgeted'],
            $account['total']['variance'],
            $account['total']['variance_percentage'] . '%',
        ];

        return $rows;
    }

    public function headings(): array
    {
        // Headings are included in the array method for simplicity
        return [];
    }

    public function styles(Worksheet $sheet)
    {
        // Apply styles to headers and totals
        $sheet->getStyle('A1:B3')->applyFromArray([
            'font' => ['bold' => true],
        ]);
        $sheet->getStyle('A5:F5')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'D3D3D3'],
            ],
        ]);
        $lastRow = count($this->records['account']['periods']) + 6; // Adjust for header rows
        $sheet->getStyle("A{$lastRow}:F{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // Auto-size columns
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
}