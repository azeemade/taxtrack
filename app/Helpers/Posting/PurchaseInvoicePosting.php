<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Posting\AccountHelper;

class PurchaseInvoicePosting
{
    public function syncPurchaseInvoiceJournal(object $pi, int $companyId, ?int $editedBy = null): int
    {
        // 1) Pull line items (must include account_id)
        $lineItems = DB::table('line_items')
            ->where('documentable_id', $pi->id)
            ->where('documentable_type', 'App\\Models\\PurchaseInvoice')
            ->get([
                'id',
                'quantity',
                'price',
                'discount',
                'vat',
                'amount',
                'category_id',
                'account_id',   // ← per-line override
                'cost_price',
            ]);

        if ($lineItems->isEmpty()) {
            throw new \RuntimeException('No line items found for this purchase invoice.');
        }

        // 2) Totals
        $debitsByAccount = [];
        $vatTotal = 0.0;
        $grossFromLines = 0.0;

        foreach ($lineItems as $li) {
            $qty       = max((float)($li->quantity ?? 0), 0.0);
            $unitPrice = (float)($li->price ?? 0.0);
            $unitTotal = $qty * $unitPrice;

            $discVal   = (float)($li->discount ?? 0.0);
            $vatVal    = (float)($li->vat ?? 0.0);
            $lineGross = $li->amount !== null ? (float)$li->amount : null;

            $discountAmt = $this->discountAmount($unitTotal, $discVal);
            $netExVat    = max($unitTotal - $discountAmt, 0.0);
            $vatAmt      = $this->vatAmount($netExVat, $vatVal, $lineGross);

            $vatTotal       += $vatAmt;
            $grossFromLines += ($netExVat + $vatAmt);

            // 3) Resolve debit account: line.account_id → category map → fallback
            $accId = $this->resolveLineDebitAccountId($li, $companyId);

            $debitsByAccount[$accId] = ($debitsByAccount[$accId] ?? 0.0) + $netExVat;
        }

        $shipping    = (float)($pi->shipping_charge ?? 0.0);
        $otherCharge = (float)($pi->additional_charge ?? 0.0);

        $computedTotal = $grossFromLines + $shipping + $otherCharge;
        $apCredit      = (float)($pi->purchase_invoices_total ?? $computedTotal);

        // 4) Controls (all expenses in your CoA — we won’t try to hit assets)
        $accAP       = $this->accountIdFromKeyOrSlug('AP',           'trade-accounts-payable',   $companyId);
        $accVATInput = $this->accountIdFromKeyOrSlug('VAT_INPUT',    'input-vat',                $companyId);
        $accFreight  = $this->accountIdFromKeyOrSlug('FREIGHT_COSTS','freight-costs',            $companyId);
        $accAdminExp = $this->accountIdFromKeyOrSlug('ADMIN_EXP',    'administrative-expenses',  $companyId);
        $accRounding = $this->accountIdFromKeyOptional('ROUNDING_DIFF', $companyId)
                      ?? $this->accountIdFromSlug('suspense-account', $companyId);

        // 5) Upsert header + lines
        return DB::transaction(function () use (
            $pi,$companyId,$editedBy,$debitsByAccount,$vatTotal,$shipping,$otherCharge,
            $apCredit,$accAP,$accVATInput,$accFreight,$accAdminExp,$accRounding,$computedTotal
        ) {
            if (!empty($pi->journal_entry_id)) {
                $jeId = (int)$pi->journal_entry_id;
                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $pi->invoice_end_date ?? $pi->invoice_start_date ?? now(),
                    'info'       => 'Purchase Invoice '.$this->safeStr($pi->purchase_invoiceID ?? $pi->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'updated_at' => now(),
                    'type'       => 'purchase_invoice',
                ]);
                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $pi->invoice_end_date ?? $pi->invoice_start_date ?? now(),
                    'info'       => 'Purchase Invoice '.$this->safeStr($pi->purchase_invoiceID ?? $pi->id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'purchase_invoice',
                ]);
                DB::table('purchase_invoices')->where('id', $pi->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($pi,'journal_entry_id')) $pi->journal_entry_id = $jeId;
            }

            $date = $pi->invoice_end_date ?? $pi->invoice_start_date ?? now();
            $ref  = $this->safeStr($pi->purchase_invoiceID ?? $pi->id);

            $entries = [];

            foreach ($debitsByAccount as $accId => $amt) {
                if ($amt > 0) $entries[] = $this->line($jeId, $date, (int)$accId, $ref, 'Expense (net)', $amt, 0);
            }
            if ($vatTotal > 0)    $entries[] = $this->line($jeId, $date, $accVATInput, $ref, 'VAT Input (recoverable)', $vatTotal, 0);
            if ($shipping > 0)    $entries[] = $this->line($jeId, $date, $accFreight,  $ref, 'Freight/Shipping', $shipping, 0);
            if ($otherCharge > 0) $entries[] = $this->line($jeId, $date, $accAdminExp, $ref, 'Additional charges', $otherCharge, 0);
            if ($apCredit > 0)    $entries[] = $this->line($jeId, $date, $accAP,       $ref, 'Accounts Payable', 0, $apCredit);

            $diff = round($apCredit - $computedTotal, 2);
            if (abs($diff) >= 0.01) {
                $entries[] = $diff > 0
                    ? $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', 0, $diff)
                    : $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', -$diff, 0);
            }

            foreach ($entries as $e) DB::table('finance_account_entries')->insert($e);

            $d = 0.0; $c = 0.0;
            foreach ($entries as $e) { $d += $e['debit_amount']; $c += $e['credit_amount']; }
            if (abs($d - $c) > 0.01) {
                Log::error("Purchase Invoice JE out of balance", compact('d','c'));
                throw new \RuntimeException('Purchase Invoice journal not balanced.');
            }

            return $jeId;
        });
    }

    // ---------- helpers ----------

    private function resolveLineDebitAccountId(object $li, int $companyId): int
    {
        // 1) Per-line account_id (override)
        if (!empty($li->account_id)) return (int)$li->account_id;

        // 2) Category map → expense slug
        $catSlug = null;
        if (!empty($li->category_id)) {
            $catSlug = DB::table('categories')->where('id', $li->category_id)->value('slug');
        }

        if ($catSlug) {
            $map = config('inventory.category_account_map', []); // ← use inventory.php
            if (isset($map[$catSlug])) {
                $acctSlug = is_array($map[$catSlug]) ? $map[$catSlug][0] : $map[$catSlug];
                $acctId   = AccountHelper::id($acctSlug, $companyId);
                if ($acctId) return (int)$acctId;
            }
        }

        // 3) Fallback to 'inventory'
        $fallbackSlug = config('inventory.fallback_expense_slug', 'purchases');
        $fallbackId   = AccountHelper::id($fallbackSlug, $companyId);
        if ($fallbackId) return (int)$fallbackId;

        // last resort (should exist in your expense CoA)
        return $this->accountIdFromSlug('purchases', $companyId);
    }

    private function discountAmount(float $unitTotal, float $discountField): float
    {
        if ($discountField <= 0) return 0.0;
        if ($discountField > 0 && $discountField <= 1)   return round($unitTotal * $discountField, 2);
        if ($discountField > 1 && $discountField <= 100) return round($unitTotal * ($discountField / 100), 2);
        return round(min($discountField, $unitTotal), 2);
    }

    private function vatAmount(float $netExVat, float $vatField, ?float $lineGross): float
    {
        if ($vatField <= 0) return 0.0;
        if ($vatField > 0 && $vatField <= 1)   return round($netExVat * $vatField, 2);
        if ($vatField > 1 && $vatField <= 100) {
            $calc = round($netExVat * ($vatField / 100), 2);
            if ($lineGross && abs(($netExVat + $calc) - $lineGross) <= 0.01) return $calc;
            if ($lineGross && $lineGross > $netExVat) return round($lineGross - $netExVat, 2);
            return $calc;
        }
        return round(max($vatField, 0.0), 2);
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

    private function safeStr($v): string { return (string)($v ?? ''); }
}
