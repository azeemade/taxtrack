<?php

namespace App\Jobs;

use App\Contracts\BulkUploadContract;
use App\Imports\DynamicBulkUploadImport;
use App\Models\BulkUploadJob;
use App\Notifications\BulkUploadCompletedNotification;
use App\Services\CloudinaryErrorReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProcessBulkUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected BulkUploadJob $bulkUploadJob;
    protected BulkUploadContract $bulkUploadHandler;
    protected string $filePath;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(BulkUploadJob $bulkUploadJob, BulkUploadContract $bulkUploadHandler, string $filePath)
    {
        $this->bulkUploadJob = $bulkUploadJob;
        $this->bulkUploadHandler = $bulkUploadHandler;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting bulk upload processing', [
                'job_id' => $this->bulkUploadJob->id,
                'module' => $this->bulkUploadHandler->getModuleName(),
            ]);

            // Update job status to processing
            $this->bulkUploadJob->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Import the Excel file
            $import = new DynamicBulkUploadImport($this->bulkUploadHandler);
            Excel::import($import, $this->filePath);

            // Get processing results
            $results = $import->prepareDataForStorage();
            $statistics = $results['statistics'];
            $errors = $results['errors'];
            $warnings = $results['warnings'];

            // Store the processed data if there are successful rows
            $storedData = [];
            if ($statistics['successful_rows'] > 0) {
                $storedData = $this->storeProcessedData($results['processed_data']);
            }

            // Generate error report if there are errors
            $errorReportPath = null;
            if (!empty($errors) || !empty($warnings)) {
                $errorReportPath = CloudinaryErrorReportService::generateAndUploadErrorReport(
                    $errors,
                    $warnings,
                    $this->bulkUploadHandler->getModuleName(),
                    $this->bulkUploadJob->id
                );
            }

            // Update job with results
            $this->bulkUploadJob->update([
                'status' => 'completed',
                'completed_at' => now(),
                'statistics' => $statistics,
                'errors' => $errors,
                'warnings' => $warnings,
                'stored_data_count' => count($storedData),
                'error_report_path' => $errorReportPath,
            ]);

            // Send notification to user
            $this->notifyUser($statistics, $errors, $warnings, $errorReportPath);

            Log::info('Bulk upload processing completed', [
                'job_id' => $this->bulkUploadJob->id,
                'statistics' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk upload processing failed', [
                'job_id' => $this->bulkUploadJob->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update job with error
            $this->bulkUploadJob->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            // Send failure notification
            $this->notifyUserOnFailure($e->getMessage());

            throw $e;
        } finally {
            // Clean up the uploaded file
            $this->cleanupFile();
        }
    }

    /**
     * Store the processed data in the database
     */
    protected function storeProcessedData(array $processedData): array
    {
        $modelClass = $this->bulkUploadHandler->getModelClass();
        $storedData = [];

        foreach ($processedData as $data) {
            try {
                $model = new $modelClass($data);
                $model->save();
                $storedData[] = $model;
            } catch (\Exception $e) {
                Log::error('Failed to store bulk upload data', [
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $storedData;
    }


    /**
     * Notify user of completion
     */
    protected function notifyUser(array $statistics, array $errors, array $warnings, ?string $errorReportPath): void
    {
        try {
            $user = $this->bulkUploadJob->user;
            $user->notify(new BulkUploadCompletedNotification(
                $this->bulkUploadJob,
                $statistics,
                $errors,
                $warnings,
                $errorReportPath
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send bulk upload notification', [
                'job_id' => $this->bulkUploadJob->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify user of failure
     */
    protected function notifyUserOnFailure(string $errorMessage): void
    {
        try {
            $user = $this->bulkUploadJob->user;
            $user->notify(new BulkUploadCompletedNotification(
                $this->bulkUploadJob,
                [],
                [$errorMessage],
                [],
                null,
                true // is failure
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send bulk upload failure notification', [
                'job_id' => $this->bulkUploadJob->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up uploaded file
     */
    protected function cleanupFile(): void
    {
        try {
            if (Storage::exists($this->filePath)) {
                Storage::delete($this->filePath);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup bulk upload file', [
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk upload job failed permanently', [
            'job_id' => $this->bulkUploadJob->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->bulkUploadJob->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $exception->getMessage(),
        ]);

        $this->notifyUserOnFailure($exception->getMessage());
        $this->cleanupFile();
    }
}
