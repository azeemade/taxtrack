<?php

namespace App\Http\Controllers;

use App\Contracts\BulkUploadContract;
use App\Exports\DynamicBulkUploadTemplateExport;
use App\Http\Requests\BulkUploadRequest;
use App\Imports\DynamicBulkUploadImport;
use App\Jobs\ProcessBulkUploadJob;
use App\Models\BulkUploadJob;
use App\Services\BulkUploadService;
use App\Services\CloudinaryErrorReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BulkUploadController extends Controller
{
    protected BulkUploadService $bulkUploadService;

    public function __construct(BulkUploadService $bulkUploadService)
    {
        $this->bulkUploadService = $bulkUploadService;
    }

    /**
     * Get available modules for bulk upload
     */
    public function getAvailableModules(): JsonResponse
    {
        $modules = $this->bulkUploadService->getAvailableModules();

        // Enhance modules with template types
        $enhancedModules = [];
        foreach ($modules as $module) {
            $handler = $this->bulkUploadService->getBulkUploadHandler($module['key']);
            $templateTypes = $handler ? $handler->getAvailableTemplateTypes() : [];

            $enhancedModules[] = array_merge($module, [
                'template_types' => $templateTypes,
                'has_multiple_templates' => count($templateTypes) > 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $enhancedModules,
        ]);
    }

    /**
     * Download template for a specific module
     */
    public function downloadTemplate(string $module, Request $request): BinaryFileResponse|JsonResponse
    {
        try {
            $bulkUploadHandler = $this->bulkUploadService->getBulkUploadHandler($module);

            if (!$bulkUploadHandler) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found or not supported for bulk upload',
                ], 404);
            }

            $subType = $request->query('type'); // Optional template type

            $export = new DynamicBulkUploadTemplateExport($bulkUploadHandler, $subType);

            $filename = $bulkUploadHandler->getModuleName() . '_bulk_upload_template';
            if ($subType) {
                $filename .= '_' . $subType;
            }
            $filename .= '.xlsx';

            return Excel::download($export, $filename);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload and process bulk data
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
            'process_type' => 'sometimes|in:sync,async',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $module = $request->input('module');
            $processType = $request->input('process_type', 'async');
            $file = $request->file('file');

            // Get the bulk upload handler for the module
            $bulkUploadHandler = $this->bulkUploadService->getBulkUploadHandler($module);

            if (!$bulkUploadHandler) {
                return response()->json([
                    'success' => false,
                    'message' => 'Module not found or not supported for bulk upload',
                ], 404);
            }

            // Validate file size based on module settings
            $maxRows = $bulkUploadHandler->getMaxRows();
            if (!$this->validateFileSize($file, $maxRows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File contains too many rows. Maximum allowed: ' . $maxRows,
                ], 422);
            }

            // Store the uploaded file
            $filePath = $this->storeUploadedFile($file, $module);

            // Create bulk upload job record
            $bulkUploadJob = BulkUploadJob::create([
                'user_id' => auth()->id(),
                'module_name' => $bulkUploadHandler->getModuleName(),
                'model_class' => $bulkUploadHandler->getModelClass(),
                'file_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'status' => 'pending',
            ]);

            // Process based on type
            if ($processType === 'sync' || !$bulkUploadHandler->shouldProcessAsync()) {
                $result = $this->processSync($bulkUploadHandler, $filePath);
                $bulkUploadJob->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'statistics' => $result['statistics'],
                    'errors' => $result['errors'],
                    'warnings' => $result['warnings'],
                    'stored_data_count' => $result['stored_data_count'],
                ]);
            } else {
                // Dispatch async job
                ProcessBulkUploadJob::dispatch($bulkUploadJob, $bulkUploadHandler, $filePath);
            }

            return response()->json([
                'success' => true,
                'message' => $processType === 'sync' ? 'Bulk upload completed successfully' : 'Bulk upload job queued successfully',
                'data' => [
                    'job_id' => $bulkUploadJob->id,
                    'status' => $bulkUploadJob->status,
                    'process_type' => $processType,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get bulk upload job status
     */
    public function getJobStatus(int $jobId): JsonResponse
    {
        try {
            $job = BulkUploadJob::where('user_id', auth()->id())->findOrFail($jobId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $job->id,
                    'module_name' => $job->module_name,
                    'status' => $job->status,
                    'statistics' => $job->formatted_statistics,
                    'success_rate' => $job->success_rate,
                    'error_rate' => $job->error_rate,
                    'processing_duration' => $job->processing_duration,
                    'created_at' => $job->created_at,
                    'completed_at' => $job->completed_at,
                    'error_message' => $job->error_message,
                    'has_error_report' => !is_null($job->error_report_path),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }
    }

    /**
     * Get user's bulk upload jobs
     */
    public function getUserJobs(Request $request): JsonResponse
    {
        $query = BulkUploadJob::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by module
        if ($request->has('module')) {
            $query->where('module_name', $request->input('module'));
        }

        $jobs = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * Download error report
     */
    public function downloadErrorReport(int $jobId): BinaryFileResponse|JsonResponse
    {
        try {
            $job = BulkUploadJob::where('user_id', auth()->id())->findOrFail($jobId);

            if (!$job->error_report_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error report not found',
                ], 404);
            }

            // Download file content from Cloudinary
            $fileContent = CloudinaryErrorReportService::downloadErrorReport($job->error_report_path);

            if (!$fileContent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to download error report from storage',
                ], 404);
            }

            $filename = 'bulk_upload_errors_' . $job->module_name . '_' . $job->id . '.xlsx';

            // Return file as download
            return response($fileContent)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download error report',
            ], 500);
        }
    }

    /**
     * Delete bulk upload job
     */
    public function deleteJob(int $jobId): JsonResponse
    {
        try {
            $job = BulkUploadJob::where('user_id', auth()->id())->findOrFail($jobId);

            // Clean up files
            if ($job->file_path && Storage::exists($job->file_path)) {
                Storage::delete($job->file_path);
            }

            // Clean up error report from Cloudinary
            if ($job->error_report_path) {
                CloudinaryErrorReportService::deleteErrorReport($job->error_report_path);
            }

            $job->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bulk upload job deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete job',
            ], 500);
        }
    }

    /**
     * Process bulk upload synchronously
     */
    protected function processSync(BulkUploadContract $bulkUploadHandler, string $filePath): array
    {
        $import = new DynamicBulkUploadImport($bulkUploadHandler);
        Excel::import($import, $filePath);

        $results = $import->prepareDataForStorage();

        // Store data immediately for sync processing
        $storedData = [];
        if ($results['statistics']['successful_rows'] > 0) {
            $storedData = $this->storeProcessedDataSync($results['processed_data'], $bulkUploadHandler->getModelClass());
        }

        return [
            'statistics' => $results['statistics'],
            'errors' => $results['errors'],
            'warnings' => $results['warnings'],
            'stored_data_count' => count($storedData),
        ];
    }

    /**
     * Store processed data synchronously
     */
    protected function storeProcessedDataSync(array $processedData, string $modelClass): array
    {
        $storedData = [];

        foreach ($processedData as $data) {
            try {
                $model = new $modelClass($data);
                $model->save();
                $storedData[] = $model;
            } catch (\Exception $e) {
                // Log error but continue processing
                \Log::error('Failed to store bulk upload data', [
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $storedData;
    }

    /**
     * Validate file size based on maximum rows
     */
    protected function validateFileSize($file, int $maxRows): bool
    {
        try {
            // Quick check by reading first few rows to estimate total
            $reader = \Maatwebsite\Excel\Facades\Excel::toArray(new \stdClass, $file);
            $totalRows = count($reader[0] ?? []);

            return $totalRows <= $maxRows + 1; // +1 for header
        } catch (\Exception $e) {
            // If we can't read the file, allow it and let the job handle validation
            return true;
        }
    }

    /**
     * Store uploaded file
     */
    protected function storeUploadedFile($file, string $module): string
    {
        $filename = time() . '_' . $module . '_' . $file->getClientOriginalName();
        $path = 'bulk-uploads/' . $module . '/' . $filename;

        Storage::putFileAs('bulk-uploads/' . $module, $file, $filename);

        return $path;
    }
}
