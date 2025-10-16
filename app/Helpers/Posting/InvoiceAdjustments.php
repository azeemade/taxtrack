<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;
use App\Helpers\Posting\AccountProvisioner;
use Illuminate\Support\Facades\Log;

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
                'info'       => 'Bad debt write-off for Invoice ' . ($invoice->invoiceID ?? $invoice->id) . ($note ? " - {$note}" : ''),
                'edited_by'  => $editedBy,
                'status'     => 'published',
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
    // public function voidInvoice(object $invoice): ?int
    // {
    //     $origJe = (int)($invoice->journal_entry_id ?? 0);
    //     if (!$origJe) return null;

    //     // Prevent double reversal
    //     $existing = DB::table('finance_journal_entries')
    //         ->where('parent_journal_entry_id', $origJe)
    //         ->where('type', 'reversal')
    //         ->value('id');
    //     if ($existing) return (int)$existing;

    //     $companyId = (int)$invoice->company_id;
    //     $date      = now();
    //     $editedBy  = (int)($invoice->created_by ?? null);

    //     $lines = DB::table('finance_account_entries')
    //         ->where('journal_entry_id', $origJe)
    //         ->get(['account_id','debit_amount','credit_amount','reference','description'])
    //         ->toArray();

    //     if (empty($lines)) return null;

    //     return DB::transaction(function () use ($invoice, $companyId, $editedBy, $date, $origJe, $lines) {
    //         $jeId = DB::table('finance_journal_entries')->insertGetId([
    //             'date'       => $date,
    //             'info'       => 'Reversal of Invoice '.($invoice->invoiceID ?? $invoice->id),
    //             'edited_by'  => $editedBy,
    //             'status'     => 'published',
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //             'company_id' => $companyId,
    //             'parent_journal_entry_id' => $origJe,
    //             'type'       => 'reversal',
    //         ]);

    //         $rev = [];
    //         foreach ($lines as $l) {
    //             $rev[] = [
    //                 'journal_entry_id' => $jeId,
    //                 'date'             => $date,
    //                 'account_id'       => (int)$l->account_id,
    //                 'reference'        => 'REV',
    //                 'description'      => 'Reversal: '.$l->description,
    //                 'debit_amount'     => (float)$l->credit_amount, // swap
    //                 'credit_amount'    => (float)$l->debit_amount,  // swap
    //                 'amount'           => (float)$l->credit_amount - (float)$l->debit_amount,
    //                 'transaction_date' => $date,
    //                 'edited_by'        => $editedBy,
    //                 'created_at'       => now(),
    //                 'updated_at'       => now(),
    //             ];
    //         }
    //         foreach ($rev as $r) DB::table('finance_account_entries')->insert($r);
    //         $this->assertBalanced($rev);

    //         return $jeId;
    //     });
    // }

    public function voidInvoice(object $invoice, ?string $voidDate = null, bool $force = false): ?int
    {
        $origJe = (int)($invoice->journal_entry_id ?? 0);
        if ($origJe <= 0) {
            Log::warning('voidInvoice: invoice has no journal_entry_id', ['invoice_id' => $invoice->id ?? null]);
            return null;
        }

        // Block if payments/credits exist (unless force)
        // if (!$force) {
        //     $hasPayments = DB::table('payment_records')->where('invoice_id', $invoice->id)->exists();
        //     $hasCredits  = DB::table('credit_note_invoices')->where('invoice_id', $invoice->id)->exists();
        //     if ($hasPayments || $hasCredits) {
        //         throw new \RuntimeException('Cannot void invoice: payments or credit notes exist.');
        //     }
        // }

        // Idempotency: already reversed?
        $existingRev = DB::table('finance_journal_entries')
            ->where('parent_journal_entry_id', $origJe)
            ->where('type', 'reversal')
            ->value('id');
        if ($existingRev) {
            Log::info('voidInvoice: reversal already exists', ['reversal_je_id' => $existingRev, 'orig_je' => $origJe]);
            return (int)$existingRev;
        }

        $date     = $voidDate ?: ($invoice->created_at ?? now());
        $editedBy = (int)($invoice->updated_by ?? $invoice->created_by ?? null);
        $companyId = (int)($invoice->company_id ?? 0);

        // Pull original lines
        $origLines = DB::table('finance_account_entries')
            ->where('journal_entry_id', $origJe)
            ->get(['account_id', 'debit_amount', 'credit_amount', 'reference', 'description']);

        if ($origLines->isEmpty()) {
            Log::warning('voidInvoice: original JE has no lines', ['orig_je' => $origJe]);
            return null;
        }

        return DB::transaction(function () use ($invoice, $origJe, $origLines, $date, $editedBy, $companyId) {
            // Create reversal header
            $revJeId = DB::table('finance_journal_entries')->insertGetId([
                'date'                     => $date,
                'info'                     => 'Reversal of Invoice ' . ($invoice->invoiceID ?? $invoice->id),
                'edited_by'                => $editedBy,
                'status'                   => 'published',
                'created_at'               => now(),
                'updated_at'               => now(),
                'company_id'               => $companyId,
                'parent_journal_entry_id'  => $origJe,
                'type'                     => 'reversal',
            ]);

            // Build reversed lines (swap Dr/Cr)
            $revLines = [];
            foreach ($origLines as $l) {
                $debit  = (float)$l->credit_amount; // swap
                $credit = (float)$l->debit_amount;  // swap
                $revLines[] = [
                    'journal_entry_id' => $revJeId,
                    'date'             => $date,
                    'account_id'       => (int)$l->account_id,
                    'reference'        => $l->reference ?: ($invoice->invoiceID ?? 'REV'),
                    'description'      => 'Reversal: ' . $l->description,
                    'debit_amount'     => round($debit, 2),
                    'credit_amount'    => round($credit, 2),
                    'amount'           => round($debit - $credit, 2),
                    'transaction_date' => $date,
                    'edited_by'        => $editedBy,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            // Balance check (local)
            $d = 0.0;
            $c = 0.0;
            foreach ($revLines as $rl) {
                $d += $rl['debit_amount'];
                $c += $rl['credit_amount'];
            }
            if (abs($d - $c) > 0.01) {
                throw new \RuntimeException('Reversal not balanced.');
            }

            // Persist lines
            foreach ($revLines as $rl) {
                DB::table('finance_account_entries')->insert($rl);
            }

            // Mark invoice as void & zero due (if you persist it)
            DB::table('invoices')->where('id', $invoice->id)->update([
                'status'         => 'void',
                'payment_status' => 'void',
                'amount_due'     => 0,
                'updated_at'     => now(),
            ]);

            // If you maintain inventory movements, reverse them here too (not shown)

            return $revJeId;
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
        $d = 0;
        $c = 0;
        foreach ($lines as $l) {
            $d += $l['debit_amount'];
            $c += $l['credit_amount'];
        }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Adjustment journal not balanced.');
    }
}
