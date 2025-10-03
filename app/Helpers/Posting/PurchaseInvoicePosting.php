<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class PurchaseInvoicePosting
{
    /**
     * Idempotent: create/refresh the journal for a Purchase Invoice.
     *
     * Posting (summary):
     *   Dr Purchases/Expenses (net of VAT, per lines)
     *   Dr VAT Input (recoverable VAT)
     *   Dr Freight costs (shipping_charge) [optional]
     *   Dr Administrative expenses (additional_charge) [optional]
     *   Cr Accounts Payable (gross total)
     *
     * @return int journal_entry_id
     */
    public function syncPurchaseInvoiceJournal(object $pi, int $companyId, ?int $editedBy = null): int
    {
        // ---- Gather line items (documentable: purchase_invoices) ----
        $lineItems = DB::table('line_items')
            ->where('documentable_id', $pi->id)
            ->where('documentable_type', 'purchase_invoices') // align with your enums if needed
            ->get([
                'id', 'quantity', 'price', 'total_unit_price', 'discount', 'vat', 'amount',
                // optional columns if you have them:
                'account_id', 'account_slug'
            ]);

        if ($lineItems->isEmpty()) {
            throw new \RuntimeException('No line items found for this purchase invoice.');
        }

        // ---- Resolve control accounts (config key OR fallback slug) ----
        $accAP        = $this->accountIdFromKeyOrSlug('AP', 'trade-accounts-payable', $companyId);
        $accVATInput  = $this->accountIdFromKeyOrSlug('VAT_INPUT', 'input-vat', $companyId);
        $accFreight   = $this->accountIdFromKeyOrSlug('FREIGHT_COSTS', 'freight-costs', $companyId);
        $accAdminExp  = $this->accountIdFromKeyOrSlug('ADMIN_EXP', 'administrative-expenses', $companyId);
        $accCOGS      = $this->accountIdFromKeyOrSlug('COGS', 'cost-of-goods-sold', $companyId); // default fallback for line debits

        // ---- Aggregate debits per account (allows future per-line account mapping) ----
        $debitsByAccount = [];  // [account_id => amount]
        $sumVAT = 0.0;

        foreach ($lineItems as $li) {
            $unitTotal = (float)($li->total_unit_price ?? 0);    // qty * price
            $discount  = (float)($li->discount ?? 0);
            $vatAmt    = (float)($li->vat ?? 0);                 // VAT amount (not rate)
            $net       = max($unitTotal - $discount, 0.0);       // net (ex VAT)

            // pick debit account for this line (explicit account_id/slug if present, else COGS)
            $lineAccId = $this->resolveLineDebitAccountId($li, $companyId, $accCOGS);

            // accumulate net to its debit account
            $debitsByAccount[$lineAccId] = ($debitsByAccount[$lineAccId] ?? 0) + $net;

            // accumulate VAT Input
            $sumVAT += $vatAmt;
        }

        $shipping  = (float)($pi->shipping_charge ?? 0.0);
        $addn      = (float)($pi->additional_charge ?? 0.0);

        // total debits (excluding VAT & charges for now) + VAT + charges
        $sumNet = array_sum($debitsByAccount);
        $gross  = $sumNet + $sumVAT + $shipping + $addn;  // what hits AP

        // ---- Upsert header + refresh lines (idempotent) ----
        return DB::transaction(function () use ($pi, $companyId, $editedBy, $debitsByAccount, $sumVAT, $shipping, $addn, $gross, $accAP, $accVATInput, $accFreight, $accAdminExp) {

            // 1) Header
            if (!empty($pi->journal_entry_id)) {
                $jeId = (int)$pi->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $pi->invoice_end_date ?? $pi->invoice_start_date ?? now(),
                    'info'       => 'Purchase Invoice '.($pi->purchase_invoiceID ?? $pi->id).' for vendor '.($pi->vendor_id ?? ''),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => 'purchase_invoice',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $pi->invoice_end_date ?? $pi->invoice_start_date ?? now(),
                    'info'       => 'Purchase Invoice '.($pi->purchase_invoiceID ?? $pi->id).' for vendor '.($pi->vendor_id ?? ''),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'purchase_invoice',
                ]);

                DB::table('purchase_invoices')->where('id', $pi->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($pi, 'journal_entry_id')) $pi->journal_entry_id = $jeId;
            }

            $date = $pi->invoice_end_date ?? $pi->invoice_start_date ?? now();
            $ref  = (string)($pi->purchase_invoiceID ?? $pi->id);

            // 2) Lines (balanced)
            $lines = [];

            // Dr per-account nets (COGS / expense / inventory etc.)
            foreach ($debitsByAccount as $accId => $amt) {
                if ($amt > 0) {
                    $lines[] = $this->line($jeId, $date, (int)$accId, $ref, 'Purchases (net)', $amt, 0);
                }
            }

            // Dr VAT Input
            if ($sumVAT > 0) {
                $lines[] = $this->line($jeId, $date, $accVATInput, $ref, 'VAT Input (recoverable)', $sumVAT, 0);
            }

            // Dr Freight costs (shipping)
            if ($shipping > 0) {
                $lines[] = $this->line($jeId, $date, $accFreight, $ref, 'Freight/Shipping charges', $shipping, 0);
            }

            // Dr Administrative expenses (additional charges)
            if ($addn > 0) {
                $lines[] = $this->line($jeId, $date, $accAdminExp, $ref, 'Additional charges', $addn, 0);
            }

            // Cr Accounts Payable
            if ($gross > 0) {
                $lines[] = $this->line($jeId, $date, $accAP, $ref, 'Accounts Payable', 0, $gross);
            }

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            return $jeId;
        });
    }

    // ---------- helpers ----------

    private function resolveLineDebitAccountId(object $li, int $companyId, int $fallbackAccId): int
    {
        // If you store an explicit account on the line, use it
        if (property_exists($li, 'account_id') && $li->account_id) {
            return (int)$li->account_id;
        }
        if (property_exists($li, 'account_slug') && $li->account_slug) {
            $id = AccountHelper::id($li->account_slug, $companyId);
            if ($id) return (int)$id;
        }
        // Fallback: COGS (or whatever you configured)
        return $fallbackAccId;
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id   = AccountHelper::id($slug, $companyId);
        if (!$id) {
            throw new \RuntimeException("Account not found for key/slug '$key' ('$slug') for company $companyId");
        }
        return (int)$id;
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
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
        $d = 0; $c = 0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) {
            throw new \RuntimeException('Purchase Invoice journal not balanced.');
        }
    }
}
