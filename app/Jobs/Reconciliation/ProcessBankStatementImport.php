<?php

namespace App\Jobs\Reconciliation;

use App\Imports\Banking\BankStatementImport;
use App\Imports\Banking\BankStatementImportQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessBankStatementImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $accountId;
    protected string $filePath;
    protected int $companyId;
    protected int $createdBy;

    public function __construct(int $accountId, string $filePath, int $companyId, int $createdBy)
    {
        $this->accountId = $accountId;
        $this->filePath = $filePath;
        $this->companyId = $companyId;
        $this->createdBy = $createdBy;
    }

    public function handle()
    {
        try {
            DB::beginTransaction();

            Excel::import(new BankStatementImportQueue($this->accountId, $this->companyId, $this->createdBy), $this->filePath);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info("An error occured " . $e);
            // Log the error if needed, e.g., \Log::error($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Storage::delete($this->filePath);
    }
}