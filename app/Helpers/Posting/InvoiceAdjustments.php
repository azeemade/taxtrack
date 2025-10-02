<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;
use App\Helpers\Posting\AccountProvisioner;

class InvoiceAdjustments
{
    /**
     * Write off an invoice amount.
     * Dr Bad Debt Expense (auto-create if missing)
     * Cr Accounts Receivable
     *
     * Returns journal_entry_id
     */
    public function postBadDebt(object $invoice, float $amount, ?string $note = null): int
    {
        if ($amount <= 0) throw new \InvalidArgumentException('Write-off amount must be > 0');

        $companyId = (int)$invoice->company_id;
        $date      = now();
        $editedBy  = (int)($invoice->created_by ?? null);

        // Ensure Bad Debt Expense account
        $badDebtSlug = config('accounts.BAD_DEBT_EXP');
        $badDebtId = AccountHelper::id($badDebtSlug, $companyId);
        if (!$badDebtId) {
            $badDebtId = AccountProvisioner::ensure($companyId, $badDebtSlug, 'Bad Debt Expense', 'administrative-expenses');
        }

        $accAR = AccountHelper::id(config('accounts.AR'), $companyId);

        return DB::transaction(function () use ($invoice, $companyId, $editedBy, $date, $amount, $badDebtId, $accAR, $note) {
            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'date'       => $date,
                'info'       => 'Bad debt write-off for Invoice '.($invoice->invoiceID ?? $invoice->id).($note? " - {$note}": ''),
                'edited_by'  => $editedBy,
                'status'     => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
                'company_id' => $companyId,
                'parent_journal_entry_id' => $invoice->journal_entry_id ?? null,
                'type'       => 'bad_debt_writeoff',
            ]);

            $lines = [
                // Dr Bad Debt Expense
                $this->line($jeId, $date, $badDebtId, 'BadDebt', 'Bad debt expense', $amount, 0),
                // Cr Accounts Receivable
                $this->line($jeId, $date, $accAR, 'BadDebt', 'Write-off AR', 0, $amount),
            ];

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            return $jeId;
        });
    }

    /**
     * Void invoice: create one reversing journal for the invoice’s original journal (if any).
     * Does NOT delete the original journal; it reverses it (audit-safe).
     */
    public function voidInvoice(object $invoice): ?int
    {
        $origJe = (int)($invoice->journal_entry_id ?? 0);
        if (!$origJe) return null;

        // Prevent double reversal
        $existing = DB::table('finance_journal_entries')
            ->where('parent_journal_entry_id', $origJe)
            ->where('type', 'reversal')
            ->value('id');
        if ($existing) return (int)$existing;

        $companyId = (int)$invoice->company_id;
        $date      = now();
        $editedBy  = (int)($invoice->created_by ?? null);

        $lines = DB::table('finance_account_entries')
            ->where('journal_entry_id', $origJe)
            ->get(['account_id','debit_amount','credit_amount','reference','description'])
            ->toArray();

        if (empty($lines)) return null;

        return DB::transaction(function () use ($invoice, $companyId, $editedBy, $date, $origJe, $lines) {
            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'date'       => $date,
                'info'       => 'Reversal of Invoice '.($invoice->invoiceID ?? $invoice->id),
                'edited_by'  => $editedBy,
                'status'     => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
                'company_id' => $companyId,
                'parent_journal_entry_id' => $origJe,
                'type'       => 'reversal',
            ]);

            $rev = [];
            foreach ($lines as $l) {
                $rev[] = [
                    'journal_entry_id' => $jeId,
                    'date'             => $date,
                    'account_id'       => (int)$l->account_id,
                    'reference'        => 'REV',
                    'description'      => 'Reversal: '.$l->description,
                    'debit_amount'     => (float)$l->credit_amount, // swap
                    'credit_amount'    => (float)$l->debit_amount,  // swap
                    'amount'           => (float)$l->credit_amount - (float)$l->debit_amount,
                    'transaction_date' => $date,
                    'edited_by'        => $editedBy,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            foreach ($rev as $r) DB::table('finance_account_entries')->insert($r);
            $this->assertBalanced($rev);

            return $jeId;
        });
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
        if (!$accountId) throw new \InvalidArgumentException("Missing account for {$desc}");
        return [
            'journal_entry_id' => $jeId,
            'date'             => $date,
            'account_id'       => $accountId,
            'reference'        => $ref,
            'description'      => $desc,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
            'amount'           => $debit - $credit,
            'transaction_date' => $date,
            'edited_by'        => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    private function assertBalanced(array $lines): void
    {
        $d=0; $c=0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Adjustment journal not balanced.');
    }
}
