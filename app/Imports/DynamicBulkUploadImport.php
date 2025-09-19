<?php

namespace App\Imports;

use App\Abstracts\BulkUploadAbstract;
use App\Contracts\BulkUploadContract;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class DynamicBulkUploadImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure, WithBatchInserts, WithChunkReading
{
    use Importable, SkipsFailures;

    protected BulkUploadContract $bulkUploadHandler;
    protected array $processedData = [];
    protected int $currentRow = 0;
    protected ?int $userId = null;
    protected ?int $companyId = null;

    public function __construct(BulkUploadContract $bulkUploadHandler, ?int $userId = null, ?int $companyId = null)
    {
        $this->bulkUploadHandler = $bulkUploadHandler;
        $this->userId = $userId;
        $this->companyId = $companyId;

        // Set user context if provided
        if ($userId && $companyId && $bulkUploadHandler instanceof \App\Abstracts\BulkUploadAbstract) {
            $bulkUploadHandler->setUserContext($userId, $companyId);
        }
    }

    /**
     * Get validation rules from the bulk upload handler
     */
    public function rules(): array
    {
        return $this->bulkUploadHandler->getValidationRules();
    }

    /**
     * Get custom validation messages
     */
    public function customValidationMessages(): array
    {
        return $this->bulkUploadHandler->getValidationMessages();
    }

    /**
     * Process each row
     */
    public function model(array $row): ?object
    {
        $this->currentRow++;

        // Skip empty rows
        if ($this->isEmptyRow($row)) {
            return null;
        }

        try {
            $this->bulkUploadHandler->incrementProcessedRows();

            $processedData = $this->bulkUploadHandler->processRow($row, $this->currentRow);

            if ($processedData === null) {
                $this->bulkUploadHandler->incrementFailedRows();
                return null;
            }

            $this->bulkUploadHandler->incrementSuccessfulRows();
            $this->processedData[] = $processedData;

            // Create model instance if needed for immediate processing
            if (!$this->bulkUploadHandler->shouldProcessAsync()) {
                $modelClass = $this->bulkUploadHandler->getModelClass();
                return new $modelClass($processedData);
            }

            return null;
        } catch (\Exception $e) {
            $this->bulkUploadHandler->addError("Row {$this->currentRow}: " . $e->getMessage());
            $this->bulkUploadHandler->incrementFailedRows();
            return null;
        }
    }

    /**
     * Get batch size for processing
     */
    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Get chunk size for reading
     */
    public function chunkSize(): int
    {
        return 100;
    }

    /**
     * Get the processed data
     */
    public function getProcessedData(): array
    {
        return $this->processedData;
    }

    /**
     * Get the bulk upload handler
     */
    public function getBulkUploadHandler(): BulkUploadContract
    {
        return $this->bulkUploadHandler;
    }

    /**
     * Check if a row is empty
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!is_null($value) && $value !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Prepare the data for storage
     */
    public function prepareDataForStorage(): array
    {
        return [
            'processed_data' => $this->processedData,
            'statistics' => $this->bulkUploadHandler->getStatistics(),
            'errors' => $this->bulkUploadHandler->getErrors(),
            'warnings' => $this->bulkUploadHandler->getWarnings(),
            'module_name' => $this->bulkUploadHandler->getModuleName(),
            'model_class' => $this->bulkUploadHandler->getModelClass(),
        ];
    }

    /**
     * Process the prepared data and create actual records
     * This should be called after the import is complete
     */
    public function processPreparedData(): array
    {
        if ($this->bulkUploadHandler instanceof \App\Abstracts\BulkUploadAbstract) {
            return $this->bulkUploadHandler->processPreparedData($this->processedData);
        }

        // Fallback for handlers that don't implement post-creation processing
        return [
            'created_records' => [],
            'errors' => ['Post-creation processing not implemented for this handler'],
            'total_created' => 0,
            'total_failed' => 1
        ];
    }
}
