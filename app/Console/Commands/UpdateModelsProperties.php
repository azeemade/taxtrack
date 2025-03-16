<?php

namespace App\Console\Commands;

use App\Enums\FinancialDocumentStatusEnums;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateModelsProperties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-models-properties';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentDate = Carbon::now();
        $this->markSalesInvoiceAsOverdue($currentDate);
    }

    protected function markSalesInvoiceAsOverdue($currentDate)
    {
        //set invoice as overdue
        $invoices = Invoice::whereNotIn('status', [
            FinancialDocumentStatusEnums::VOID->value,
            FinancialDocumentStatusEnums::OVERDUE->value
        ])
            ->whereDate('due_date', "2025-03-15")
            ->get();
        // ->filter(fn($invoice) => floatval($invoice->amount_due) > 0);
        dump($invoices);

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => FinancialDocumentStatusEnums::OVERDUE->value]);
        }
    }
}
