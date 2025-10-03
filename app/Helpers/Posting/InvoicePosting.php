<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;

class InvoicePosting
{
    /**
     * Create or refresh the journal for an invoice (idempotent).
     * - Inserts header if missing; otherwise clears & re-posts lines.
     * - Persists journal_entry_id on invoice (and source quote if present).
     *
     * @return int The journal_entry_id
     */
    public function syncInvoiceJournal(object $invoice, int $companyId, ?int $editedBy = null): int
    {
        // Totals (adjust property names if your schema differs)
        $subTotal     = (float) ($invoice->sub_total ?? 0);
        $discount     = (float) ($invoice->discount_total ?? 0);       // total discount on invoice
        $vat          = (float) ($invoice->tax_total ?? 0);            // VAT on sales
        $shipping     = (float) ($invoice->shipping_charge ?? 0);
        $otherCharges = (float) ($invoice->additional_charge ?? 0);

        $netSales   = max($subTotal - $discount, 0.0);
        $receivable = $netSales + $vat + $shipping + $otherCharges;

        // Resolve account IDs via slugs (from config/accounts.php)
        $accAR         = $this->accountIdFromKey('AR', $companyId);
        $accSALES      = $this->accountIdFromKey('SALES', $companyId);
        $accVATPayable = $this->accountIdFromKey('VAT_PAYABLE', $companyId);
        $accDiscounts  = $this->accountIdFromKey('DISCOUNTS', $companyId);

        return DB::transaction(function () use (
            $invoice, $companyId, $editedBy, $netSales, $discount, $vat, $receivable,
            $accAR, $accSALES, $accVATPayable, $accDiscounts
        ) {
            // 1) Upsert journal header
            if (!empty($invoice->journal_entry_id)) {
                $jeId = (int) $invoice->journal_entry_id;

                // Refresh header
                DB::table('finance_journal_entries')
                    ->where('id', $jeId)
                    ->update([
                        'date'       => $invoice->invoice_date ?? now(),
                        'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                        'edited_by'  => $editedBy,
                        'status'     => 'posted',
                        'updated_at' => now(),
                        'type'       => 'sales_invoice',
                    ]);

                // Clear existing lines
                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $invoice->invoice_date ?? now(),
                    'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'sales_invoice',
                ]);

                // Persist on invoice (and source quote if any)
                DB::table('invoices')->where('id', $invoice->id)->update(['journal_entry_id' => $jeId]);
                if (!empty($invoice->quote_id)) {
                    DB::table('quotes')->where('id', $invoice->quote_id)->update(['journal_entry_id' => $jeId]);
                }

                // Make it available to caller object if it's an Eloquent model
                if (property_exists($invoice, 'journal_entry_id')) {
                    $invoice->journal_entry_id = $jeId;
                }
            }

            // 2) Build balanced lines
            $journalDate = $invoice->invoice_date ?? now();
            $reference   = $this->safeStr($invoice->invoiceID);

            $lines = [];

            // AR (Debit)
            if ($receivable > 0) {
                $lines[] = $this->line($jeId, $journalDate, $accAR, $reference, 'Accounts Receivable', $receivable, 0);
            }

            // Sales (Credit)
            if ($netSales > 0) {
                $lines[] = $this->line($jeId, $journalDate, $accSALES, $reference, 'Sales revenue', 0, $netSales);
            }

            // Discounts (Debit, contra-revenue)
            if ($discount > 0) {
                $lines[] = $this->line($jeId, $journalDate, $accDiscounts, $reference, 'Sales discount', $discount, 0);
            }

            // VAT Payable (Credit)
            if ($vat > 0) {
                $lines[] = $this->line($jeId, $journalDate, $accVATPayable, $reference, 'VAT on sales', 0, $vat);
            }

            // 3) Insert lines
            foreach ($lines as $l) {
                DB::table('finance_account_entries')->insert($l);
            }

            // 4) (Optional) sanity check: enforce balance
            $totals = array_reduce($lines, fn($c, $l) => [
                'd' => $c['d'] + $l['debit_amount'],
                'c' => $c['c'] + $l['credit_amount'],
            ], ['d' => 0.0, 'c' => 0.0]);

            if (abs($totals['d'] - $totals['c']) > 0.0001) {
                // rollback will occur automatically due to exception inside transaction
                throw new \RuntimeException('Journal not balanced (debits != credits).');
            }

            return $jeId;
        });
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
        if (!$accountId) {
            throw new \InvalidArgumentException("Missing account mapping for {$desc}");
        }

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

        return (int) $id;
    }

    private function safeStr($value): string
    {
        return (string) ($value ?? '');
    }
}