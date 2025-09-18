<?php

namespace App\Exports\Banking;

use App\Models\ReconciliationRun;
use App\Models\ReconciliationRecord;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Contracts\View\View;

class ReconciliationRunExport implements FromView, WithTitle
{
    protected $runId;
    protected $companyId;

    public function __construct(int $runId, int $companyId)
    {
        $this->runId = $runId;
        $this->companyId = $companyId;
    }

    public function view(): View
    {
        $run = ReconciliationRun::where('company_id', $this->companyId)
            ->findOrFail($this->runId);

        $linesQuery = ReconciliationRecord::where('company_id', $this->companyId)
            ->where('run_id', $this->runId)
            ->orderBy('date')
            ->orderBy('id');

        $lines = $linesQuery->get();
        $stats = $this->summarizeLines($lines->collect());

        return view('exports.reconciliation-run', [
            'run' => $run,
            'stats' => $stats,
            'lines' => $lines,
        ]);
    }

    public function title(): string
    {
        return 'Reconciliation Run ' . $this->runId;
    }

    private function summarizeLines($lines)
    {
        return [
            'total_records' => $lines->count(),
            // Add other stats as needed
        ];
    }
}