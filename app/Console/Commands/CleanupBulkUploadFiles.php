<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\BulkUploadJob;

class CleanupBulkUploadFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulk-upload:cleanup {--hours=5 : Number of hours after which files should be deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up bulk upload files older than specified hours (default: 5 hours)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);

        $this->info("Cleaning up bulk upload files older than {$hours} hours...");
        $this->info("Cutoff time: {$cutoffTime->format('Y-m-d H:i:s')}");

        $deletedFiles = 0;
        $deletedDirectories = 0;
        $errors = 0;

        try {
            // Get all files in the bulk-uploads directory
            $allFiles = Storage::allFiles('bulk-uploads');

            if (empty($allFiles)) {
                $this->info('No bulk upload files found to clean up.');
                return Command::SUCCESS;
            }

            foreach ($allFiles as $file) {
                try {
                    // Get file modification time
                    $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

                    // If file is older than cutoff time, delete it
                    if ($lastModified->lt($cutoffTime)) {
                        if (Storage::delete($file)) {
                            $deletedFiles++;
                            $this->line("Deleted: {$file} (modified: {$lastModified->format('Y-m-d H:i:s')})");
                        } else {
                            $errors++;
                            $this->error("Failed to delete: {$file}");
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("Error processing file {$file}: " . $e->getMessage());
                    Log::error('Bulk upload cleanup error', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Clean up empty directories
            $directories = $this->getEmptyDirectories('bulk-uploads');
            foreach ($directories as $directory) {
                try {
                    if (Storage::deleteDirectory($directory)) {
                        $deletedDirectories++;
                        $this->line("Deleted empty directory: {$directory}");
                    }
                } catch (\Exception $e) {
                    $this->warn("Failed to delete directory {$directory}: " . $e->getMessage());
                }
            }

            // Log cleanup results
            Log::info('Bulk upload files cleanup completed', [
                'deleted_files' => $deletedFiles,
                'deleted_directories' => $deletedDirectories,
                'errors' => $errors,
                'cutoff_hours' => $hours,
                'cutoff_time' => $cutoffTime->toISOString()
            ]);

            // Also clean up old job records and their associated files
            $this->info('Cleaning up old bulk upload job records...');
            $jobCleanupResults = BulkUploadJob::cleanupOldJobs($hours);

            $deletedJobs = $jobCleanupResults['deleted_jobs'];
            $deletedJobFiles = $jobCleanupResults['deleted_files'];
            $jobErrors = $jobCleanupResults['errors'];
            $errors += $jobErrors;

            // Display summary
            $this->newLine();
            $this->info('Cleanup Summary:');
            $this->line("✓ Files deleted: {$deletedFiles}");
            $this->line("✓ Directories deleted: {$deletedDirectories}");
            $this->line("✓ Job records deleted: {$deletedJobs}");
            $this->line("✓ Job files deleted: {$deletedJobFiles}");

            if ($errors > 0) {
                $this->warn("⚠ Errors encountered: {$errors}");
            }

            $this->newLine();
            $this->info('Bulk upload files cleanup completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to cleanup bulk upload files: ' . $e->getMessage());
            Log::error('Bulk upload cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get all empty directories in the specified path
     *
     * @param string $path
     * @return array
     */
    private function getEmptyDirectories(string $path): array
    {
        $emptyDirectories = [];

        try {
            $directories = Storage::directories($path);

            foreach ($directories as $directory) {
                // Check if directory is empty (no files or subdirectories)
                $files = Storage::files($directory);
                $subDirectories = Storage::directories($directory);

                if (empty($files) && empty($subDirectories)) {
                    $emptyDirectories[] = $directory;
                } else {
                    // Recursively check subdirectories
                    $subEmptyDirectories = $this->getEmptyDirectories($directory);
                    $emptyDirectories = array_merge($emptyDirectories, $subEmptyDirectories);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error getting empty directories', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
        }

        return $emptyDirectories;
    }
}
