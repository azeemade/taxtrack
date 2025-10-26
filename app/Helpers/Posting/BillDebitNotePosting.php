<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Posting\AccountHelper;

class BillDebitNotePosting
{
    /**
     * Post or refresh a Debit Note against a bill (reduces A/P).
     *
     * JE:
     *   Dr Accounts Payable (gross of debit note)
     *   Cr Expense(s) (net per line)
     *   Cr VAT Input (recoverable portion)
     * + rounding guard if UI total != computed
     *
     * Guards:
     *   - Bill must be ISSUED
     *   - Debit note gross <= bill open balance
     *
     * @param object $debitNote (expects: id, bill_id, total?, journal_entry_id?, note_date?)
     * @param object $bill      VendorBill (expects: id, company_id, total, status)
     * @param array  $lines     Each: ['account_id'=>int, 'net'=>float, 'vat'=>float|null, 'vat_rate'=>float|null, 'gross'=>float|null, 'desc'=>?string]
     *                          - Provide either 'vat' or 'vat_rate' (0..1 or 0..100). If 'gross' provided, used for consistency check.
     * @param int    $companyId
     * @param int|null $editedBy
     * @return int journal_entry_id
     */
    public function syncDebitNoteJournal(object $debitNote, object $bill, array $lines, int $companyId, ?int $editedBy = null): int
    {
        // Guards
        $status = (string)($bill->status ?? '');
        if (!in_array($status, ['issued','published','ISSUED','PUBLISHED'], true)) {
            throw new \RuntimeException('Bill must be ISSUED to receive a debit note.');
        }

        // Compute totals
        $sumNet = 0.0; $sumVAT = 0.0; $sumGross = 0.0;
        $prepared = [];

        foreach ($lines as $ln) {
            $accId   = (int)($ln['account_id'] ?? 0);
            $net     = round((float)($ln['net'] ?? 0), 2);
            $vat     = $ln['vat'] ?? null;
            $rate    = $ln['vat_rate'] ?? null;
            $grossUi = $ln['gross'] ?? null;

            if ($accId <= 0 || $net <= 0) throw new \InvalidArgumentException('Each line needs valid account_id and net > 0.');

            if ($vat === null) {
                $vatField = (float)($rate ?? 0);
                if ($vatField <= 0) {
                    $vatAmt = 0.0;
                } elseif ($vatField > 0 && $vatField <= 1) {
                    $vatAmt = round($net * $vatField, 2);
                } elseif ($vatField > 1 && $vatField <= 100) {
                    $vatAmt = round($net * ($vatField / 100), 2);
                } else {
                    $vatAmt = round(max($vatField, 0.0), 2); // absolute
                }
            } else {
                $vatAmt = round((float)$vat, 2);
            }

            $gross = $net + $vatAmt;
            if ($grossUi !== null && abs($gross - (float)$grossUi) > 0.01) {
                // prefer UI gross if provided and plausible
                $gross = round((float)$grossUi, 2);
                $vatAmt = round(max($gross - $net, 0.0), 2);
            }

            $sumNet   += $net;
            $sumVAT   += $vatAmt;
            $sumGross += ($net + $vatAmt);

            $prepared[] = [
                'account_id' => $accId,
                'net'        => $net,
                'vat'        => $vatAmt,
                'gross'      => $net + $vatAmt,
                'desc'       => (string)($ln['desc'] ?? 'Debit note adjustment'),
            ];
        }

        // Open-balance guard
        $open = $this->billOpenBalance((int)$bill->id, $companyId);
        if ($sumGross - $open > 0.009) {
            throw new \InvalidArgumentException('Debit note exceeds bill open balance.');
        }

        // Resolve accounts
        $accAP       = $this->accountIdFromKeyOrSlug('AP', 'trade-accounts-payable', $companyId);
        $accVATInput = $this->accountIdFromKeyOrSlug('VAT_INPUT', 'input-vat', $companyId);
        $accRounding = $this->accountIdFromKeyOptional('ROUNDING_DIFF', $companyId)
                      ?? $this->accountIdFromSlug('suspense-account', $companyId);

        // JE header + lines
        return DB::transaction(function () use ($debitNote, $bill, $companyId, $editedBy, $prepared, $sumNet, $sumVAT, $sumGross, $accAP, $accVATInput, $accRounding) {
            if (!empty($debitNote->journal_entry_id)) {
                $jeId = (int)$debitNote->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $debitNote->note_date ?? now(),
                    'info'       => 'Debit Note '.$this->ref($debitNote->id).' for bill '.$this->ref($bill->vendor_billID ?? $bill->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'updated_at' => now(),
                    'type'       => 'vendor_debit_note',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $debitNote->note_date ?? now(),
                    'info'       => 'Debit Note '.$this->ref($debitNote->id).' for bill '.$this->ref($bill->vendor_billID ?? $bill->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'vendor_debit_note',
                ]);

                DB::table('vendor_bill_debit_notes')->where('id', $debitNote->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($debitNote, 'journal_entry_id')) $debitNote->journal_entry_id = $jeId;
            }

            $date = $debitNote->note_date ?? now();
            $ref  = $this->ref($debitNote->id);
            $entries = [];

            // Dr A/P (gross)
            if ($sumGross > 0) {
                $entries[] = $this->line($jeId, $date, $accAP, $ref, 'Reduce A/P (debit note)', $sumGross, 0);
            }

            // Cr expense(s) (net)
            foreach ($prepared as $ln) {
                if ($ln['net'] > 0) {
                    $entries[] = $this->line($jeId, $date, (int)$ln['account_id'], $ref, $ln['desc'], 0, $ln['net']);
                }
            }

            // Cr VAT Input
            if ($sumVAT > 0) {
                $entries[] = $this->line($jeId, $date, $accVATInput, $ref, 'Reverse VAT Input (debit note)', 0, $sumVAT);
            }

            // Rounding guard (if UI total present on debit note)
            $uiTotal = isset($debitNote->total) ? round((float)$debitNote->total, 2) : $sumGross;
            $diff    = round($uiTotal - $sumGross, 2);
            if (abs($diff) >= 0.01) {
                if ($diff > 0) {
                    // More gross per UI than computed → Dr rounding to balance
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', $diff, 0);
                } else {
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', 0, -$diff);
                }
            }

            foreach ($entries as $e) DB::table('finance_account_entries')->insert($e);
            $this->assertBalanced($entries);

            // OPTIONAL: allocation record
            // DB::table('bill_debit_note_allocations')->updateOrInsert(
            //     ['debit_note_id' => $debitNote->id, 'bill_id' => $bill->id],
            //     ['amount' => $uiTotal, 'created_at'=>now(), 'updated_at'=>now()]
            // );

            return $jeId;
        });
    }

    // ---------- helpers ----------

    private function billOpenBalance(int $billId, int $companyId): float
    {
        $total = (float) (DB::table('vendor_bills')->where('id', $billId)->value('total') ?? 0);
        $paid  = (float) (DB::table('bill_payment_allocations')->where('bill_id', $billId)->sum(DB::raw('amount + COALESCE(discount_component,0)')) ?? 0);
        $dns   = (float) (DB::table('bill_debit_note_allocations')->where('bill_id', $billId)->sum('amount') ?? 0);
        return max(round($total - $paid - $dns, 2), 0.0);
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account not found for key/slug '$key' ('$slug') for company $companyId");
        return (int)$id;
    }

    private function accountIdFromKeyOptional(string $key, int $companyId): ?int
    {
        $slug = config("accounts.$key");
        if (!$slug) return null;
        $id = AccountHelper::id($slug, $companyId);
        return $id ? (int)$id : null;
    }

    private function accountIdFromSlug(string $slug, int $companyId): int
    {
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account with slug '$slug' not found for company $companyId");
        return (int)$id;
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        return [
            'journal_entry_id' => $jeId,
            'date'             => $date,
            'account_id'       => $accountId,
            'reference'        => $ref,
            'description'      => $desc,
            'debit_amount'     => round($debit, 2),
            'credit_amount'    => round($credit, 2),
            'amount'           => round($debit - $credit, 2),
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
        if (abs($d - $c) > 0.01) throw new \RuntimeException('Debit Note journal not balanced.');
    }

    private function ref($v): string { return (string)($v ?? ''); }
}
