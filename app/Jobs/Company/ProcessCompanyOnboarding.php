<?php

namespace App\Jobs\Company;

use App\Models\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCompanyOnboarding implements ShouldQueue
{
    use Queueable;

    protected $company;
    /**
     * Create a new job instance.
     */
    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        event(new CompanyOnboarded($this->company));
    }
}
