<?php

namespace App\Exports;

use App\Contracts\BulkUploadContract;
use App\Services\RequestValidationService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DynamicBulkUploadTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected BulkUploadContract $bulkUploadHandler;
    protected ?string $subType;

    public function __construct(BulkUploadContract $bulkUploadHandler, ?string $subType = null)
    {
        $this->bulkUploadHandler = $bulkUploadHandler;
        $this->subType = $subType;
    }

    /**
     * Get the array data for the template
     */
    public function array(): array
    {
        return $this->bulkUploadHandler->getTemplateSampleData($this->subType);
    }

    /**
     * Get the headings for the template
     */
    public function headings(): array
    {
        return $this->bulkUploadHandler->getTemplateHeaders($this->subType);
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        // Style the header row
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '366092'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Style the sample data row
        if ($lastRow > 1) {
            $sheet->getStyle('A2:' . $lastColumn . '2')->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F2F2F2'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'font' => [
                    'italic' => true,
                    'color' => ['rgb' => '666666'],
                ],
            ]);
        }

        // Add borders to all data cells
        $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        $headers = $this->bulkUploadHandler->getTemplateHeaders($this->subType);
        $widths = [];

        foreach ($headers as $index => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            // Set width based on header length, minimum 15, maximum 30
            $widths[$column] = min(30, max(15, strlen($header) + 5));
        }

        return $widths;
    }

    /**
     * Register events
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();

                // Freeze the header row
                $sheet->freezePane('A2');

                // Add data validation comments (if needed)
                $this->addValidationComments($sheet, $lastColumn);
            },
        ];
    }

    /**
     * Add validation comments to help users
     */
    protected function addValidationComments(Worksheet $sheet, string $lastColumn): void
    {
        $headers = $this->bulkUploadHandler->getTemplateHeaders($this->subType);
        $rules = $this->bulkUploadHandler->getValidationRules();

        foreach ($headers as $index => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $headerCell = $column . '1';

            // Convert header back to field name to match validation rules
            $fieldName = $this->headerToFieldName($header);

            // Get validation rules for this column
            $columnRules = $rules ?? []; //[$fieldName] ?? [];

            if (!empty($columnRules)) {
                $comment = $this->buildValidationComment($columnRules);
                if ($comment) {
                    $sheet->getComment($headerCell)->getText()->createTextRun($comment);
                }
            }
        }
    }

    /**
     * Convert header back to field name
     */
    protected function headerToFieldName(string $header): string
    {
        // Convert "Title Case With Spaces" back to "field_name"
        return strtolower(str_replace(' ', '_', $header));
    }

    /**
     * Build validation comment text
     */
    protected function buildValidationComment(array $rules): string
    {
        $comments = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $comments[] = $this->formatRule($rule);
            }
        }

        return !empty($comments) ? implode("\n", $comments) : '';
    }

    /**
     * Format a validation rule for display
     */
    protected function formatRule(string $rule): string
    {
        return match (true) {
            str_starts_with($rule, 'required') => 'Required field',
            str_starts_with($rule, 'email') => 'Must be a valid email',
            str_starts_with($rule, 'numeric') => 'Must be a number',
            str_starts_with($rule, 'date') => 'Must be a valid date (YYYY-MM-DD)',
            str_starts_with($rule, 'min:') => 'Minimum length: ' . substr($rule, 4),
            str_starts_with($rule, 'max:') => 'Maximum length: ' . substr($rule, 4),
            str_starts_with($rule, 'unique:') => 'Must be unique',
            str_starts_with($rule, 'exists:') => 'Must exist in system',
            default => $rule,
        };
    }
}
