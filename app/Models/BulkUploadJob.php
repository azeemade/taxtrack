<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BulkUploadJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module_name',
        'model_class',
        'file_path',
        'original_filename',
        'status',
        'statistics',
        'errors',
        'warnings',
        'stored_data_count',
        'error_report_path',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'statistics' => 'array',
        'errors' => 'array',
        'warnings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the bulk upload job
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for processing jobs
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get formatted statistics
     */
    public function getFormattedStatisticsAttribute(): array
    {
        $stats = $this->statistics ?? [];

        return [
            'processed_rows' => $stats['processed_rows'] ?? 0,
            'successful_rows' => $stats['successful_rows'] ?? 0,
            'failed_rows' => $stats['failed_rows'] ?? 0,
            'total_errors' => $stats['total_errors'] ?? 0,
            'total_warnings' => $stats['total_warnings'] ?? 0,
        ];
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if job is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get processing duration
     */
    public function getProcessingDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffForHumans($this->completed_at, true);
    }

    /**
     * Get success rate
     */
    public function getSuccessRateAttribute(): float
    {
        $stats = $this->statistics ?? [];
        $processed = $stats['processed_rows'] ?? 0;
        $successful = $stats['successful_rows'] ?? 0;

        if ($processed === 0) {
            return 0;
        }

        return round(($successful / $processed) * 100, 2);
    }

    /**
     * Get error rate
     */
    public function getErrorRateAttribute(): float
    {
        $stats = $this->statistics ?? [];
        $processed = $stats['processed_rows'] ?? 0;
        $failed = $stats['failed_rows'] ?? 0;

        if ($processed === 0) {
            return 0;
        }

        return round(($failed / $processed) * 100, 2);
    }

    /**
     * Scope for jobs older than specified hours
     */
    public function scopeOlderThan($query, int $hours)
    {
        $cutoffTime = Carbon::now()->subHours($hours);
        return $query->where('created_at', '<', $cutoffTime);
    }

    /**
     * Scope for completed or failed jobs older than specified hours
     */
    public function scopeCompletedOrFailedOlderThan($query, int $hours)
    {
        $cutoffTime = Carbon::now()->subHours($hours);
        return $query->whereIn('status', ['completed', 'failed'])
            ->where('created_at', '<', $cutoffTime);
    }

    /**
     * Clean up files associated with this job
     */
    public function cleanupFiles(): bool
    {
        $success = true;

        try {
            // Clean up uploaded file
            if ($this->file_path && Storage::exists($this->file_path)) {
                if (!Storage::delete($this->file_path)) {
                    Log::warning('Failed to delete bulk upload file', [
                        'job_id' => $this->id,
                        'file_path' => $this->file_path
                    ]);
                    $success = false;
                }
            }

            // Clean up error report from Cloudinary if exists
            if ($this->error_report_path) {
                try {
                    $cloudinaryService = app(\App\Services\CloudinaryErrorReportService::class);
                    $cloudinaryService->deleteErrorReport($this->error_report_path);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete error report from Cloudinary', [
                        'job_id' => $this->id,
                        'error_report_path' => $this->error_report_path,
                        'error' => $e->getMessage()
                    ]);
                    // Don't mark as failure since this is external service
                }
            }
        } catch (\Exception $e) {
            Log::error('Error during bulk upload job file cleanup', [
                'job_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            $success = false;
        }

        return $success;
    }

    /**
     * Static method to clean up old jobs and their files
     */
    public static function cleanupOldJobs(int $hours = 5): array
    {
        $cutoffTime = Carbon::now()->subHours($hours);
        $deletedJobs = 0;
        $deletedFiles = 0;
        $errors = 0;

        try {
            // Get old completed or failed jobs
            $oldJobs = self::completedOrFailedOlderThan($hours)->get();

            foreach ($oldJobs as $job) {
                try {
                    // Clean up files first
                    if ($job->cleanupFiles()) {
                        $deletedFiles++;
                    }

                    // Delete the job record
                    $job->delete();
                    $deletedJobs++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to cleanup old bulk upload job', [
                        'job_id' => $job->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Old bulk upload jobs cleanup completed', [
                'deleted_jobs' => $deletedJobs,
                'deleted_files' => $deletedFiles,
                'errors' => $errors,
                'cutoff_hours' => $hours,
                'cutoff_time' => $cutoffTime->toISOString()
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk upload jobs cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $errors++;
        }

        return [
            'deleted_jobs' => $deletedJobs,
            'deleted_files' => $deletedFiles,
            'errors' => $errors
        ];
    }
}
