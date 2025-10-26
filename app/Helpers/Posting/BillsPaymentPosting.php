<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Posting\AccountHelper;

class BillsPaymentPosting
{
    /**
     * Post or refresh a Bill Payment journal.
     *
     * JE:
     *   Dr Accounts Payable (vendor)        amount + discount_component
     *   Cr Bank (bank_id)                   amount
     *   Cr Purchase Discounts (optional)    discount_component
     *   [+ Realised FX Gain/Loss if you handle FX — omitted here, add later]
     *
     * Guardrails:
     *   - Bill must be ISSUED (not draft/void)
     *   - amount > 0, amount <= bill_open_balance (after considering discount)
     *   - bank_id must be an active bank/cash GL in this company
     *   - Idempotent: if $payment->journal_entry_id exists, refresh its lines
     *
     * @param object $payment   (expects: id, bill_id, amount, discount?, bank_id, payment_date?, journal_entry_id?)
     * @param object $bill      VendorBill (expects: id, company_id, vendor_id, vendor_billID?, status, total?, journal_entry_id?)
     * @param int    $companyId
     * @param int|null $editedBy
     * @return int journal_entry_id
     */
    public function syncBillPaymentJournal(object $payment, object $bill, int $companyId, ?int $editedBy = null): int
    {
        // ---- Guards ----
        $this->assertBillPayable($bill);

        $amount   = round((float)($payment->amount ?? 0), 2);
        $discount = round((float)($payment->discount ?? 0), 2);
        if ($amount <= 0) throw new \InvalidArgumentException('Payment amount must be > 0.');
        if ($discount < 0) throw new \InvalidArgumentException('Discount cannot be negative.');

        $this->assertBankAccount((int)$payment->bank_id, $companyId);

        $open = $this->billOpenBalance((int)$bill->id, $companyId);
        if ($amount + $discount - $open > 0.009) {
            throw new \InvalidArgumentException('Payment + discount exceeds bill open balance.');
        }

        // ---- Resolve accounts ----
        $accAP        = $this->accountIdFromKeyOrSlug('AP', 'trade-accounts-payable', $companyId);
        $accDiscounts = $this->accountIdFromKeyOrSlug('PURCHASE_DISCOUNTS', 'purchase-discounts', $companyId);
        $accBank      = (int)$payment->bank_id;

        // ---- Upsert JE header & lines ----
        return DB::transaction(function () use ($payment, $bill, $companyId, $editedBy, $accAP, $accBank, $accDiscounts, $amount, $discount) {
            // header
            if (!empty($payment->journal_entry_id)) {
                $jeId = (int)$payment->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $payment->payment_date ?? now(),
                    'info'       => 'Bill Payment '.$this->ref($payment->id).' for bill '.$this->ref($bill->vendor_billID ?? $bill->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'updated_at' => now(),
                    'type'       => 'bill_payment',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $payment->payment_date ?? now(),
                    'info'       => 'Bill Payment '.$this->ref($payment->id).' for bill '.$this->ref($bill->vendor_billID ?? $bill->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'bill_payment',
                ]);

                DB::table('vendor_bill_payments')->where('id', $payment->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($payment, 'journal_entry_id')) $payment->journal_entry_id = $jeId;
            }

            $date = $payment->payment_date ?? now();
            $ref  = (string)($payment->reference ?? $payment->id);

            $lines = [];

            // Dr A/P for amount + discount (settles liability)
            $settled = $amount + $discount;
            $lines[] = $this->line($jeId, $date, $accAP, $ref, 'Settle A/P', $settled, 0);

            // Cr Bank for cash outflow
            $lines[] = $this->line($jeId, $date, $accBank, $ref, 'Bank outflow', 0, $amount);

            // Cr Purchase Discounts if any
            if ($discount > 0) {
                $lines[] = $this->line($jeId, $date, $accDiscounts, $ref, 'Early payment discount', 0, $discount);
            }

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            // OPTIONAL: allocations (adjust table names to your schema)
            // DB::table('bill_payment_allocations')->updateOrInsert(
            //     ['payment_id' => $payment->id, 'bill_id' => $bill->id],
            //     ['amount' => $amount, 'discount_component' => $discount, 'updated_at' => now(), 'created_at' => now()]
            // );

            return $jeId;
        });
    }

    // ---------- helpers ----------

    private function assertBillPayable(object $bill): void
    {
        $status = (string)($bill->status ?? '');
        if (!in_array($status, ['issued','published','ISSUED','PUBLISHED'], true)) {
            throw new \RuntimeException('Bill must be ISSUED to make payment.');
        }
    }

    private function billOpenBalance(int $billId, int $companyId): float
    {
        // Strategy: use Bill total – payments – debit notes.
        $total = (float) (DB::table('vendor_bills')->where('id', $billId)->value('total') ?? 0);

        $paid = (float) (DB::table('bill_payment_allocations')->where('bill_id', $billId)->sum(DB::raw('amount + COALESCE(discount_component,0)')) ?? 0);

        $debitNotes = (float) (DB::table('bill_debit_note_allocations')->where('bill_id', $billId)->sum('amount') ?? 0);

        $open = round($total - $paid - $debitNotes, 2);
        return max($open, 0.0);
    }

    private function assertBankAccount(int $accountId, int $companyId): void
    {
        $row = DB::table('finance_chart_of_accounts')
            ->where('id', $accountId)
            ->where('company_id', $companyId)
            ->where('is_active', 'true')
            ->first(['id','account_sub_category_id','slug']);

        if (!$row) throw new \InvalidArgumentException('Bank account not found or inactive for this company.');

        // Optional stricter check: ensure subcategory is bank or cash
        $subSlug = DB::table('finance_account_sub_categories')->where('id', $row->account_sub_category_id)->value('slug');
        if (!in_array($subSlug, ['bank-accounts','cash-in-hand','cash-and-cash-equivalents'], true)) {
            // Allow override via config if needed
            $allow = config('accounts.allow_nonbank_payments', false);
            if (!$allow) throw new \InvalidArgumentException('Provided account is not a bank/cash account.');
        }
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account not found for key/slug '$key' ('$slug') for company $companyId");
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
        if (abs($d - $c) > 0.01) throw new \RuntimeException('Bill Payment journal not balanced.');
    }

    private function ref($v): string { return (string)($v ?? ''); }
}
