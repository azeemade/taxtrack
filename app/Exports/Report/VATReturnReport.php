<?php

namespace App\Exports\Report;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VATReturnReport implements FromArray, WithStyles
{
    protected $records;

    public function __construct($records)
    {
        $this->records = $records;
    }

    public function array(): array
    {
        $rows = [];

        // Company info
        $rows[] = ['Company Name', $this->records['company_name'] ?? ''];
        $rows[] = ['VAT Number', $this->records['vat_number'] ?? ''];
        $rows[] = ['Quarter Ending', $this->records['quarter_ending'] ?? ''];
        $rows[] = ['VAT Rate', $this->records['vat_rate'] ?? ''];
        $rows[] = ['Line Total', $this->records['line_total'] ?? 0];

        // === two explicit blank rows (each with two empty cells) ===
        $rows[] = ['', ''];
        $rows[] = ['', ''];

        // Table header
        $rows[] = ['Description', 'Value'];

        // Lines
        if (!empty($this->records['lines']) && is_array($this->records['lines'])) {
            foreach ($this->records['lines'] as $line) {
                $rows[] = [
                    $line['description'] ?? '',
                    $line['value'] ?? 0,
                ];
            }
        }

        // Total (no extra blank rows after the table header in your requested layout)
        $rows[] = ['Total', $this->records['line_total'] ?? 0];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Bold the company info labels (A1:A5)
        $sheet->getStyle('A1:A5')->applyFromArray([
            'font' => ['bold' => true],
        ]);

        // Find the header row dynamically (the row that contains 'Description' in column A)
        $highestRow = $sheet->getHighestRow();
        $headerRow = null;
        for ($i = 1; $i <= $highestRow; $i++) {
            $val = $sheet->getCell("A{$i}")->getValue();
            if (is_string($val) && trim($val) === 'Description') {
                $headerRow = $i;
                break;
            }
        }

        if ($headerRow) {
            $sheet->getStyle("A{$headerRow}:B{$headerRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD3D3D3'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ],
            ]);
        }

        // Style the last row (Total)
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A{$lastRow}:B{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFB7DEE8'], // soft blue
            ],
        ]);

        // Auto-size columns
        foreach (range('A', 'B') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [];
    }
}
