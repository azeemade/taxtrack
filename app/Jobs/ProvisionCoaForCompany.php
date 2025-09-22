<?php

namespace App\Jobs;

use App\Helpers\Posting\CoaProvisionerFromConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionCoaForCompany implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $companyId;
    protected $editedBy;

    /**
     * Create a new job instance.
     *
     * @param int $companyId
     * @param int|null $editedBy
     */
    public function __construct(int $companyId, ?int $editedBy = null)
    {
        $this->companyId = $companyId;
        $this->editedBy = $editedBy;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $provisioner = new CoaProvisionerFromConfig();
            $result = $provisioner->provisionForCompany($this->companyId, $this->editedBy);

            Log::info('COA provisioning completed for company ID: ' . $this->companyId, [
                'created_sub_categories' => $result['createdSub'],
                'updated_sub_categories' => $result['updatedSub'],
                'created_accounts' => $result['createdAcc'],
                'updated_accounts' => $result['updatedAcc'],
            ]);
        } catch (\Throwable $e) {
            Log::error('COA provisioning failed for company ID: ' . $this->companyId, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw to allow Laravel to handle retries or mark as failed
        }
    }
}