<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Posting\AccountHelper;

class VendorBillPosting
{
    /**
     * Idempotent: create/refresh the journal for a Vendor Bill.
     *
     * Posting (summary):
     *   Dr Expense(s) per line (net of VAT; per-line account_id override, else category map → expense)
     *   Dr VAT Input (recoverable VAT)
     *   Dr Freight/Shipping (shipping_charge)            [optional]
     *   Dr Administrative expenses (additional_charge)   [optional]
     *   Cr Accounts Payable (gross)
     *
     * + Rounding/suspense if UI total != computed.
     *
     * @param object   $bill       VendorBill model (id, company_id, vendor_billID?, vendor_id?,
     *                             shipping_charge?, additional_charge?, total?, journal_entry_id?)
     * @param int      $companyId
     * @param int|null $editedBy
     * @return int journal_entry_id
     * @throws \Throwable
     */
    public function syncVendorBillJournal(object $bill, int $companyId, ?int $editedBy = null): int
    {
        // 1) Pull line items (morph: App\Models\VendorBill)
        $lineItems = DB::table('line_items')
            ->where('documentable_id', $bill->id)
            ->where('documentable_type', 'App\\Models\\VendorBill')
            ->get([
                'id',
                'quantity',
                'price',
                'discount',
                'vat',
                'amount',        // optional UI gross for consistency check
                'category_id',
                'account_id',    // per-line override
            ]);

        if ($lineItems->isEmpty()) {
            throw new \RuntimeException('No line items found for this vendor bill.');
        }

        // 2) Compute totals & group debits per expense account
        $debitsByAccount = []; // [account_id => amount]
        $vatTotal        = 0.0;
        $grossFromLines  = 0.0;

        foreach ($lineItems as $li) {
            $qty       = max((float)($li->quantity ?? 0.0), 0.0);
            $unitPrice = (float)($li->price ?? 0.0);
            $unitTotal = $qty * $unitPrice; // BEFORE discount

            $discVal   = (float)($li->discount ?? 0.0);
            $vatVal    = (float)($li->vat ?? 0.0);   // rate/fraction/absolute supported
            $lineGross = $li->amount !== null ? (float)$li->amount : null; // optional UI gross

            $discountAmt = $this->discountAmount($unitTotal, $discVal);
            $netExVat    = max($unitTotal - $discountAmt, 0.0);
            $vatAmt      = $this->vatAmount($netExVat, $vatVal, $lineGross);

            $vatTotal       += $vatAmt;
            $grossFromLines += ($netExVat + $vatAmt);

            // Determine expense account for this line:
            //  1) use line.account_id if present;
            //  2) else category→expense mapping (config/purchases.php);
            //  3) else fallback 'purchases'.
            $expAccId = $this->resolveLineExpenseAccountId($li, $companyId);

            $debitsByAccount[$expAccId] = ($debitsByAccount[$expAccId] ?? 0.0) + $netExVat;
        }

        $shipping    = (float)($bill->shipping_charge ?? 0.0);
        $otherCharge = (float)($bill->additional_charge ?? 0.0);

        // Computed total (preferred audit basis; UI total reconciled via rounding line if needed)
        $computedTotal = $grossFromLines + $shipping + $otherCharge;

        // UI/AP total (if bill->total provided, prefer that for A/P)
        $apCredit = (float)($bill->total ?? $computedTotal);

        // 3) Resolve control accounts
        $accAP         = $this->accountIdFromKeyOrSlug('AP',            'trade-accounts-payable',   $companyId);
        $accVATInput   = $this->accountIdFromKeyOrSlug('VAT_INPUT',     'input-vat',                $companyId);
        $accFreight    = $this->accountIdFromKeyOrSlug('FREIGHT_COSTS', 'freight-costs',            $companyId);
        $accAdminExp   = $this->accountIdFromKeyOrSlug('ADMIN_EXP',     'administrative-expenses',  $companyId);
        $accRounding   = $this->accountIdFromKeyOptional('ROUNDING_DIFF', $companyId)
                       ?? $this->accountIdFromSlug('suspense-account', $companyId);

        // 4) Upsert header + lines (idempotent)
        return DB::transaction(function () use (
            $bill, $companyId, $editedBy, $debitsByAccount, $vatTotal, $shipping, $otherCharge,
            $apCredit, $accAP, $accVATInput, $accFreight, $accAdminExp, $accRounding, $computedTotal
        ) {
            // Header (no parent_journal_entry_id because PI is non-posting)
            if (!empty($bill->journal_entry_id)) {
                $jeId = (int)$bill->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $bill->vendor_bill_due_date ?? $bill->vendor_bill_date ?? now(),
                    'info'       => 'Vendor Bill '.$this->safeStr($bill->vendor_billID ?? $bill->id).' for vendor '.$this->safeStr($bill->vendor_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'updated_at' => now(),
                    'type'       => 'vendor_bill',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $bill->vendor_bill_due_date ?? $bill->vendor_bill_date ?? now(),
                    'info'       => 'Vendor Bill '.$this->safeStr($bill->vendor_billID ?? $bill->id).' for vendor '.$this->safeStr($bill->vendor_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null, // PI is non-posting in your ERP
                    'type'       => 'vendor_bill',
                ]);

                DB::table('vendor_bills')->where('id', $bill->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($bill, 'journal_entry_id')) $bill->journal_entry_id = $jeId;
            }

            $date = $bill->vendor_bill_due_date ?? $bill->vendor_bill_date ?? now();
            $ref  = $this->safeStr($bill->vendor_billID ?? $bill->id);

            $entries = [];

            // Dr expenses per account
            foreach ($debitsByAccount as $accId => $amt) {
                if ($amt > 0) {
                    $entries[] = $this->line($jeId, $date, (int)$accId, $ref, 'Expense (net)', $amt, 0);
                }
            }

            // Dr VAT Input
            if ($vatTotal > 0) {
                $entries[] = $this->line($jeId, $date, $accVATInput, $ref, 'VAT Input (recoverable)', $vatTotal, 0);
            }

            // Dr Freight (shipping)
            if ($shipping > 0) {
                $entries[] = $this->line($jeId, $date, $accFreight, $ref, 'Freight/Shipping', $shipping, 0);
            }

            // Dr Admin expenses (additional charges)
            if ($otherCharge > 0) {
                $entries[] = $this->line($jeId, $date, $accAdminExp, $ref, 'Additional charges', $otherCharge, 0);
            }

            // Cr Accounts Payable
            if ($apCredit > 0) {
                $entries[] = $this->line($jeId, $date, $accAP, $ref, 'Accounts Payable', 0, $apCredit);
            }

            // Rounding / UI total reconciliation (guard)
            $diff = round($apCredit - $computedTotal, 2);
            if (abs($diff) >= 0.01) {
                if ($diff > 0) {
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', 0, $diff);
                } else {
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', -$diff, 0);
                }
            }

            // Persist & balance check
            foreach ($entries as $e) {
                DB::table('finance_account_entries')->insert($e);
            }

            $d = 0.0; $c = 0.0;
            foreach ($entries as $e) { $d += $e['debit_amount']; $c += $e['credit_amount']; }
            if (abs($d - $c) > 0.01) {
                Log::error("Vendor Bill JE out of balance", ['debits' => $d, 'credits' => $c, 'vendor_bill_id' => $bill->id]);
                throw new \RuntimeException('Vendor Bill journal not balanced (debits != credits).');
            }

            return $jeId;
        });
    }

    // ---------------- helpers ----------------

    /**
     * Expense account resolver with override and category fallback.
     * Priority:
     *   1) line.account_id
     *   2) config('purchases.category_account_map')[category_slug] → slug → id
     *   3) config('purchases.fallback_expense_slug', 'purchases')
     */
    private function resolveLineExpenseAccountId(object $li, int $companyId): int
    {
        // 1) Explicit per-line account_id
        if (!empty($li->account_id)) {
            return (int)$li->account_id;
        }

        // 2) Category → expense account mapping (config/purchases.php)
        $catSlug = null;
        if (!empty($li->category_id)) {
            $catSlug = DB::table('categories')->where('id', $li->category_id)->value('slug');
        }
        if ($catSlug) {
            $map = config('inventory.category_account_map', []);
            if (isset($map[$catSlug])) {
                $acctSlug = is_array($map[$catSlug]) ? $map[$catSlug][0] : $map[$catSlug];
                $acctId   = AccountHelper::id($acctSlug, $companyId);
                if ($acctId) return (int)$acctId;
            }
        }

        // 3) Fallback
        $fallbackSlug = config('inventory.fallback_expense_slug', 'purchases');
        $fallbackId   = AccountHelper::id($fallbackSlug, $companyId);
        if ($fallbackId) return (int)$fallbackId;

        // Last resort: must exist in your expense CoA
        return $this->accountIdFromSlug('purchases', $companyId);
    }

    private function discountAmount(float $unitTotal, float $discountField): float
    {
        if ($discountField <= 0) return 0.0;
        if ($discountField > 0 && $discountField <= 1)   return round($unitTotal * $discountField, 2);       // fraction
        if ($discountField > 1 && $discountField <= 100) return round($unitTotal * ($discountField / 100), 2); // percent
        return round(min($discountField, $unitTotal), 2); // absolute
    }

    private function vatAmount(float $netExVat, float $vatField, ?float $lineGross): float
    {
        if ($vatField <= 0) return 0.0;
        if ($vatField > 0 && $vatField <= 1)   return round($netExVat * $vatField, 2); // fraction
        if ($vatField > 1 && $vatField <= 100) {
            $calc = round($netExVat * ($vatField / 100), 2); // percent
            // Prefer UI gross if consistent
            if ($lineGross && abs(($netExVat + $calc) - $lineGross) <= 0.01) return $calc;
            if ($lineGross && $lineGross > $netExVat) return round($lineGross - $netExVat, 2);
            return $calc;
        }
        return round(max($vatField, 0.0), 2); // absolute VAT amount
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id   = AccountHelper::id($slug, $companyId);
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

    private function safeStr($v): string
    {
        return (string)($v ?? '');
    }
}
