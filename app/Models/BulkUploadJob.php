<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
