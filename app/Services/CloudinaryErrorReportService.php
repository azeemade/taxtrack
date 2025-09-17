<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CloudinaryErrorReportService
{
    /**
     * Generate and upload error report to Cloudinary
     *
     * @param array $errors
     * @param array $warnings
     * @param string $moduleName
     * @param int $jobId
     * @return string|null Cloudinary URL
     */
    public static function generateAndUploadErrorReport(
        array $errors,
        array $warnings,
        string $moduleName,
        int $jobId
    ): ?string {
        try {
            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Error Type');
            $sheet->setCellValue('B1', 'Message');
            $sheet->setCellValue('C1', 'Row');
            $sheet->setCellValue('D1', 'Field');
            $sheet->setCellValue('E1', 'Timestamp');

            // Style headers
            $sheet->getStyle('A1:E1')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FF6B6B'],
                ],
            ]);

            $row = 2;

            // Add errors
            foreach ($errors as $error) {
                $parsedError = self::parseErrorMessage($error);

                $sheet->setCellValue('A' . $row, 'Error');
                $sheet->setCellValue('B' . $row, $parsedError['message']);
                $sheet->setCellValue('C' . $row, $parsedError['row']);
                $sheet->setCellValue('D' . $row, $parsedError['field']);
                $sheet->setCellValue('E' . $row, now()->format('Y-m-d H:i:s'));
                $row++;
            }

            // Add warnings
            foreach ($warnings as $warning) {
                $parsedWarning = self::parseErrorMessage($warning);

                $sheet->setCellValue('A' . $row, 'Warning');
                $sheet->setCellValue('B' . $row, $parsedWarning['message']);
                $sheet->setCellValue('C' . $row, $parsedWarning['row']);
                $sheet->setCellValue('D' . $row, $parsedWarning['field']);
                $sheet->setCellValue('E' . $row, now()->format('Y-m-d H:i:s'));
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'E') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Generate filename
            $filename = "bulk_upload_errors_{$moduleName}_{$jobId}_" . time();

            // Save to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'bulk_upload_error_');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);

            // Upload to Cloudinary
            $uploadResult = Cloudinary::upload($tempFile, [
                'public_id' => "bulk_uploads/error_reports/{$filename}",
                'resource_type' => 'raw',
                'folder' => 'bulk_uploads/error_reports/',
            ]);

            // Clean up temporary file
            unlink($tempFile);

            return $uploadResult->getSecurePath();
        } catch (\Exception $e) {
            Log::error('Failed to generate error report', [
                'error' => $e->getMessage(),
                'module' => $moduleName,
                'job_id' => $jobId,
            ]);

            return null;
        }
    }

    /**
     * Download error report from Cloudinary URL
     *
     * @param string $cloudinaryUrl
     * @return string|null File content
     */
    public static function downloadErrorReport(string $cloudinaryUrl): ?string
    {
        try {
            $fileContent = file_get_contents($cloudinaryUrl);
            return $fileContent ?: null;
        } catch (\Exception $e) {
            Log::error('Failed to download error report from Cloudinary', [
                'url' => $cloudinaryUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete error report from Cloudinary
     *
     * @param string $cloudinaryUrl
     * @return bool
     */
    public static function deleteErrorReport(string $cloudinaryUrl): bool
    {
        try {
            // Extract public ID from URL
            $publicId = self::extractPublicIdFromUrl($cloudinaryUrl);

            if (!$publicId) {
                return false;
            }

            $result = Cloudinary::destroy($publicId, [
                'resource_type' => 'raw',
            ]);

            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            Log::error('Failed to delete error report from Cloudinary', [
                'url' => $cloudinaryUrl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Parse error message to extract components
     *
     * @param string $errorMessage
     * @return array
     */
    protected static function parseErrorMessage(string $errorMessage): array
    {
        $row = 0;
        $field = '';
        $message = $errorMessage;

        // Try to extract row number (e.g., "Row 5: Field is required")
        if (preg_match('/Row (\d+):\s*(.+)/', $errorMessage, $matches)) {
            $row = (int) $matches[1];
            $message = $matches[2];
        }

        // Try to extract field name from validation messages
        if (preg_match('/The (\w+) field/', $message, $fieldMatches)) {
            $field = $fieldMatches[1];
        }

        return [
            'row' => $row,
            'field' => $field,
            'message' => $message,
        ];
    }

    /**
     * Extract public ID from Cloudinary URL
     *
     * @param string $url
     * @return string|null
     */
    protected static function extractPublicIdFromUrl(string $url): ?string
    {
        try {
            // Parse the URL to get the path
            $parsedUrl = parse_url($url);
            $path = $parsedUrl['path'] ?? '';

            // Remove leading slash and file extension
            $path = ltrim($path, '/');
            $path = preg_replace('/\.(xlsx|xls|csv)$/', '', $path);

            return $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate error report summary
     *
     * @param array $errors
     * @param array $warnings
     * @return array
     */
    public static function generateErrorSummary(array $errors, array $warnings): array
    {
        $summary = [
            'total_errors' => count($errors),
            'total_warnings' => count($warnings),
            'errors_by_type' => [],
            'warnings_by_type' => [],
            'common_fields' => [],
        ];

        // Analyze errors
        foreach ($errors as $error) {
            $parsed = self::parseErrorMessage($error);
            $field = $parsed['field'];

            if ($field) {
                $summary['errors_by_type'][$field] = ($summary['errors_by_type'][$field] ?? 0) + 1;
                $summary['common_fields'][$field] = ($summary['common_fields'][$field] ?? 0) + 1;
            }
        }

        // Analyze warnings
        foreach ($warnings as $warning) {
            $parsed = self::parseErrorMessage($warning);
            $field = $parsed['field'];

            if ($field) {
                $summary['warnings_by_type'][$field] = ($summary['warnings_by_type'][$field] ?? 0) + 1;
                $summary['common_fields'][$field] = ($summary['common_fields'][$field] ?? 0) + 1;
            }
        }

        // Sort common fields by frequency
        arsort($summary['common_fields']);

        return $summary;
    }
}
