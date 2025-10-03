<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;

class CreditNoteMultipleLinePosting
{
    /**
     * Idempotent: create or refresh the journal for a credit note with multiple invoice line items.
     *
     * Posting logic (Sales Credit Note):
     *   Dr Sales (reverse revenue) ............. sum of net credits
     *   Dr VAT Payable (reverse VAT) ........... sum of VAT portions
     *   Dr Discounts (optional, if you pass/track allowances separately)
     *   Cr Accounts Receivable ................. total credit (net + VAT + discounts)
     *
     * @return int journal_entry_id
     */
    public function syncForCreditNote(object $creditNote, int $companyId, ?int $editedBy = null): int
    {
        // --- Load items (expected table: credit_note_items) ---
        // Required columns: credit_note_id, invoice_id, line_item_id, credit_amount, credit_in_full (bool)
        // Optional on line_items (if available): amount, tax_amount, tax_rate, discount_amount
        $items = DB::table('credit_note_items')
            ->where('credit_note_id', $creditNote->id)
            ->get(['invoice_id','line_item_id','credit_amount','credit_in_full'])
            ->toArray();

        if (empty($items)) {
            throw new \RuntimeException('No credit items found for this credit note.');
        }

        // --- Resolve control accounts from config slugs ---
        $accAR         = $this->accountIdFromKey('AR', $companyId);
        $accSALES      = $this->accountIdFromKey('SALES', $companyId);
        $accVATPayable = $this->accountIdFromKey('VAT_PAYABLE', $companyId);
        $accDiscounts  = config('accounts.DISCOUNTS') ? $this->accountIdFromKey('DISCOUNTS', $companyId) : null;

        // --- Compute aggregated amounts from items ---
        $sumNet = 0.0;         // revenue component to reverse
        $sumVAT = 0.0;         // VAT portion to reverse
        $sumDisc = 0.0;        // optional: allowances/extra discount routed to DISCOUNTS
        $sumTotal = 0.0;       // what hits AR (credit)

        foreach ($items as $it) {
            $creditGross = (float) $it->credit_amount;
            $split = $this->splitNetVatFromLineItem((int)$it->invoice_id, (int)$it->line_item_id, $creditGross);

            // $split = ['net' => x, 'vat' => y, 'discount' => z]  // discount is optional, often 0
            $sumNet   += $split['net'];
            $sumVAT   += $split['vat'];
            $sumDisc  += $split['discount'];
            $sumTotal += $creditGross; // AR credit is total gross credited back to customer
        }

        // Safety clamps
        $sumNet   = max($sumNet, 0.0);
        $sumVAT   = max($sumVAT, 0.0);
        $sumDisc  = max($sumDisc, 0.0);
        $sumTotal = max($sumTotal, 0.0);

        // --- Upsert journal header + regenerate lines (idempotent) ---
        return DB::transaction(function () use (
            $creditNote, $companyId, $editedBy,
            $accAR, $accSALES, $accVATPayable, $accDiscounts,
            $sumNet, $sumVAT, $sumDisc, $sumTotal
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
                    'parent_journal_entry_id' => $creditNote->invoice_id ? ($creditNote->invoice->journal_entry_id ?? null) : null,
                    'type'       => 'sales_credit_note',
                ]);

                DB::table('credit_notes')->where('id', $creditNote->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($creditNote, 'journal_entry_id')) {
                    $creditNote->journal_entry_id = $jeId;
                }
            }

            // 2) Build balanced lines from aggregates
            $date      = $creditNote->issue_date ?? now();
            $reference = $this->safeStr($creditNote->credit_note_number);

            $lines = [];

            // Dr Sales (reverse revenue)
            if ($sumNet > 0) {
                $lines[] = $this->line($jeId, $date, $accSALES, $reference, 'Reverse Sales (credit note)', $sumNet, 0);
            }

            // Dr VAT Payable (reverse VAT liability)
            if ($sumVAT > 0) {
                $lines[] = $this->line($jeId, $date, $accVATPayable, $reference, 'Reverse VAT on sales', $sumVAT, 0);
            }

            // Optional: Dr Discounts (if you decide to treat allowances here)
            if ($accDiscounts && $sumDisc > 0) {
                $lines[] = $this->line($jeId, $date, $accDiscounts, $reference, 'Credit note discount/allowance', $sumDisc, 0);
            }

            // Cr AR (reduce customer balance) with gross amount
            if ($sumTotal > 0) {
                $lines[] = $this->line($jeId, $date, $accAR, $reference, 'Reduce Accounts Receivable', 0, $sumTotal);
            }

            foreach ($lines as $l) {
                DB::table('finance_account_entries')->insert($l);
            }

            $this->assertBalanced($lines);

            return $jeId;
        });
    }

    /**
     * Split a credit amount into net + VAT (and optional discount) using the original line item info when available.
     * Falls back to assuming no VAT if the line has no tax info.
     *
     * Expected line_items columns if present: amount (gross), tax_amount, tax_rate, discount_amount.
     * If the credited amount is partial, proportions are applied relative to the line's gross.
     */
    private function splitNetVatFromLineItem(int $invoiceId, int $lineItemId, float $creditGross): array
    {
        $line = DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $invoiceId)
            ->where('documentable_type', 'invoices') // adjust if you use enum/value
            ->first();

        if (!$line) {
            // No line data → treat as net-only (no VAT known)
            return ['net' => $creditGross, 'vat' => 0.0, 'discount' => 0.0];
        }

        // Try to read gross/tax/discount from line if columns exist
        $gross          = $this->col($line, 'amount', $creditGross); // default to creditGross if missing
        $lineTaxAmount  = $this->col($line, 'tax_amount', null);
        $lineTaxRate    = $this->col($line, 'tax_rate', null);       // e.g., 7.5 or 0.075 (your schema)
        $lineDiscount   = $this->col($line, 'discount_amount', 0.0);

        // If we have explicit tax_amount on the line, proportionally split
        if ($gross > 0 && $lineTaxAmount !== null) {
            $p = min(max($creditGross / (float)$gross, 0.0), 1.0);
            $vat = (float)$lineTaxAmount * $p;
            $discount = (float)$lineDiscount * $p;
            $net = $creditGross - $vat; // assume gross = net + vat
            if ($net < 0) $net = 0.0;
            return ['net' => $net, 'vat' => $vat, 'discount' => $discount];
        }

        // If we have a tax rate but not amount, derive VAT:
        if ($lineTaxRate !== null) {
            $rate = (float)$lineTaxRate;
            // If someone stored 7.5 instead of 0.075, normalize:
            if ($rate > 1.0) $rate = $rate / 100.0;

            // creditGross = net * (1 + rate)
            $net = $creditGross / (1.0 + $rate);
            $vat = $creditGross - $net;
            // Pro-rate discount if gross known
            $discount = ($gross > 0) ? (float)$lineDiscount * min(max($creditGross / (float)$gross, 0.0), 1.0) : 0.0;

            return ['net' => $net, 'vat' => $vat, 'discount' => $discount];
        }

        // No tax info → treat credit as net-only
        return ['net' => $creditGross, 'vat' => 0.0, 'discount' => 0.0];
    }

    // --- Helpers -------------------------------------------------------------

    private function col(object $row, string $name, $default)
    {
        return property_exists($row, $name) ? $row->{$name} : $default;
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
        if (!$slug) {
            throw new \InvalidArgumentException("Account slug not found for key '{$key}' in config/accounts.php");
        }
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) {
            throw new \RuntimeException("Account with slug '{$slug}' not found for company {$companyId}");
        }
        return (int)$id;
    }

    private function safeStr($value): string
    {
        return (string) ($value ?? '');
    }

    private function assertBalanced(array $lines): void
    {
        $d=0; $c=0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Credit Note journal not balanced.');
    }
}
