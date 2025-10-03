<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;

class CreditNotePosting
{
    /**
     * Create or refresh the journal for a credit note (idempotent).
     *
     * @return int journal_entry_id
     */
    public function syncCreditNoteJournal(object $creditNote, int $companyId, ?int $editedBy = null): int
    {
        $total      = (float) ($creditNote->total ?? 0);
        $vat        = (float) ($creditNote->tax_total ?? 0);
        $discount   = (float) ($creditNote->discount_total ?? 0);
        $netCredit  = max($total - $vat, 0.0); // base amount excl. VAT

        // Resolve accounts
        $accAR         = $this->accountIdFromKey('AR', $companyId);
        $accSALES      = $this->accountIdFromKey('SALES', $companyId);
        $accVATPayable = $this->accountIdFromKey('VAT_PAYABLE', $companyId);
        $accDiscounts  = $this->accountIdFromKey('DISCOUNTS', $companyId);

        return DB::transaction(function () use (
            $creditNote, $companyId, $editedBy,
            $netCredit, $discount, $vat, $accAR, $accSALES, $accVATPayable, $accDiscounts
        ) {
            // 1) Upsert header
            if (!empty($creditNote->journal_entry_id)) {
                $jeId = (int)$creditNote->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note '.$this->safeStr($creditNote->credit_note_number).' for customer '.$this->safeStr($creditNote->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => 'sales_credit_note',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note '.$this->safeStr($creditNote->credit_note_number).' for customer '.$this->safeStr($creditNote->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $creditNote->invoice_id ? $creditNote->journal_entry_id : null,
                    'type'       => 'sales_credit_note',
                ]);

                DB::table('credit_notes')->where('id', $creditNote->id)->update(['journal_entry_id' => $jeId]);
                $creditNote->journal_entry_id = $jeId;
            }

            // 2) Build lines
            $date      = $creditNote->issue_date ?? now();
            $reference = $this->safeStr($creditNote->credit_note_number);

            $lines = [];

            // Sales reversal (Dr Sales)
            if ($netCredit > 0) {
                $lines[] = $this->line($jeId, $date, $accSALES, $reference, 'Reverse Sales', $netCredit, 0);
            }

            // VAT reversal (Dr VAT Payable)
            if ($vat > 0) {
                $lines[] = $this->line($jeId, $date, $accVATPayable, $reference, 'Reverse VAT on sales', $vat, 0);
            }

            // Discounts (Dr Discounts, optional if credit note is for allowance)
            if ($discount > 0) {
                $lines[] = $this->line($jeId, $date, $accDiscounts, $reference, 'Credit note discount', $discount, 0);
            }

            // AR reduction (Cr AR)
            $arTotal = $netCredit + $vat + $discount;
            if ($arTotal > 0) {
                $lines[] = $this->line($jeId, $date, $accAR, $reference, 'Reduce Accounts Receivable', 0, $arTotal);
            }

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);

            $this->assertBalanced($lines);

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

    private function accountIdFromKey(string $key, int $companyId): int
    {
        $slug = config("accounts.{$key}");
        if (!$slug) throw new \InvalidArgumentException("Account slug not found for key '{$key}'");
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account with slug '{$slug}' not found for company {$companyId}");
        return (int)$id;
    }

    private function safeStr($value): string
    {
        return (string) ($value ?? '');
    }

    private function assertBalanced(array $lines): void
    {
        $d=0; $c=0;
        foreach ($lines as $l) { $d+=$l['debit_amount']; $c+=$l['credit_amount']; }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Credit Note journal not balanced.');
    }
}
