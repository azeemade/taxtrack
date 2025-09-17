<?php

namespace App\Notifications;

use App\Models\BulkUploadJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulkUploadCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected BulkUploadJob $bulkUploadJob;
    protected array $statistics;
    protected array $errors;
    protected array $warnings;
    protected ?string $errorReportPath;
    protected bool $isFailure;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        BulkUploadJob $bulkUploadJob,
        array $statistics = [],
        array $errors = [],
        array $warnings = [],
        ?string $errorReportPath = null,
        bool $isFailure = false
    ) {
        $this->bulkUploadJob = $bulkUploadJob;
        $this->statistics = $statistics;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->errorReportPath = $errorReportPath;
        $this->isFailure = $isFailure;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage;

        if ($this->isFailure) {
            return $message
                ->subject('Bulk Upload Failed - ' . $this->bulkUploadJob->module_name)
                ->greeting('Hello ' . $notifiable->name . ',')
                ->line('Your bulk upload for ' . $this->bulkUploadJob->module_name . ' has failed.')
                ->line('Error: ' . ($this->bulkUploadJob->error_message ?? 'Unknown error'))
                ->action('View Details', url('/bulk-uploads/' . $this->bulkUploadJob->id))
                ->line('Please check the file format and try again.')
                ->salutation('Best regards, TaxTrack Team');
        }

        $successCount = $this->statistics['successful_rows'] ?? 0;
        $errorCount = $this->statistics['failed_rows'] ?? 0;
        $warningCount = count($this->warnings);

        $message = $message
            ->subject('Bulk Upload Completed - ' . $this->bulkUploadJob->module_name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your bulk upload for ' . $this->bulkUploadJob->module_name . ' has been completed.')
            ->line('✅ Successfully processed: ' . $successCount . ' records')
            ->action('View Details', url('/bulk-uploads/' . $this->bulkUploadJob->id));

        if ($errorCount > 0) {
            $message = $message->line('❌ Failed to process: ' . $errorCount . ' records');
        }

        if ($warningCount > 0) {
            $message = $message->line('⚠️ Warnings: ' . $warningCount . ' issues found');
        }

        if ($this->errorReportPath) {
            $message = $message
                ->line('An error report has been generated with details of the issues.')
                ->action('Download Error Report', $this->getErrorReportUrl());
        }

        return $message->salutation('Best regards, TaxTrack Team');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'bulk_upload_job_id' => $this->bulkUploadJob->id,
            'module_name' => $this->bulkUploadJob->module_name,
            'status' => $this->bulkUploadJob->status,
            'statistics' => $this->statistics,
            'error_count' => count($this->errors),
            'warning_count' => count($this->warnings),
            'has_error_report' => !is_null($this->errorReportPath),
            'error_report_path' => $this->errorReportPath,
            'is_failure' => $this->isFailure,
            'error_message' => $this->bulkUploadJob->error_message,
        ];
    }

    /**
     * Get the error report URL
     */
    protected function getErrorReportUrl(): string
    {
        if (!$this->errorReportPath) {
            return '';
        }

        return url('/bulk-uploads/' . $this->bulkUploadJob->id . '/error-report');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
