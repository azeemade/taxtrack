<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class VendorBillPosting
{
    /**
     * Idempotent: create/refresh the journal for a Vendor Bill.
     *
     * Same posting as Purchase Invoice. Uses bill-level dates/IDs and links parent to purchase invoice if present.
     *
     * @return int journal_entry_id
     */
    public function syncVendorBillJournal(object $bill, int $companyId, ?int $editedBy = null): int
    {
        // load purchase invoice if linked (for parent journal)
        $parentJe = null;
        if (!empty($bill->purchase_invoice_id)) {
            $parentJe = DB::table('purchase_invoices')->where('id', $bill->purchase_invoice_id)->value('journal_entry_id');
        }

        // line items are typically attached to the bill itself:
        $lineItems = DB::table('line_items')
            ->where('documentable_id', $bill->id)
            ->where('documentable_type', 'vendor_bills')
            ->get(['id','total_unit_price','discount','vat','amount','account_id','account_slug']);

        if ($lineItems->isEmpty()) {
            throw new \RuntimeException('No line items found for this vendor bill.');
        }

        // accounts
        $accAP        = $this->accountIdFromKeyOrSlug('AP', 'trade-accounts-payable', $companyId);
        $accVATInput  = $this->accountIdFromKeyOrSlug('VAT_INPUT', 'input-vat', $companyId);
        $accFreight   = $this->accountIdFromKeyOrSlug('FREIGHT_COSTS', 'freight-costs', $companyId);
        $accAdminExp  = $this->accountIdFromKeyOrSlug('ADMIN_EXP', 'administrative-expenses', $companyId);
        $accCOGS      = $this->accountIdFromKeyOrSlug('COGS', 'cost-of-goods-sold', $companyId);

        $debitsByAccount = [];
        $sumVAT = 0.0;

        foreach ($lineItems as $li) {
            $unitTotal = (float)($li->total_unit_price ?? 0);
            $discount  = (float)($li->discount ?? 0);
            $vatAmt    = (float)($li->vat ?? 0);
            $net       = max($unitTotal - $discount, 0.0);

            $accId = $this->resolveLineDebitAccountId($li, $companyId, $accCOGS);
            $debitsByAccount[$accId] = ($debitsByAccount[$accId] ?? 0) + $net;
            $sumVAT += $vatAmt;
        }

        $shipping = (float)($bill->shipping_charge ?? 0);
        $addn     = (float)($bill->additional_charge ?? 0);
        $sumNet   = array_sum($debitsByAccount);
        $gross    = $sumNet + $sumVAT + $shipping + $addn;

        return DB::transaction(function () use ($bill, $companyId, $editedBy, $parentJe, $debitsByAccount, $sumVAT, $shipping, $addn, $gross, $accAP, $accVATInput, $accFreight, $accAdminExp) {

            if (!empty($bill->journal_entry_id)) {
                $jeId = (int)$bill->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $bill->vendor_bill_due_date ?? now(),
                    'info'       => 'Vendor Bill '.($bill->vendor_billID ?? $bill->id).' for vendor '.($bill->vendor_id ?? ''),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => 'vendor_bill',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $bill->vendor_bill_due_date ?? now(),
                    'info'       => 'Vendor Bill '.($bill->vendor_billID ?? $bill->id).' for vendor '.($bill->vendor_id ?? ''),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => $parentJe ?: null,
                    'type'       => 'vendor_bill',
                ]);

                DB::table('vendor_bills')->where('id', $bill->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($bill, 'journal_entry_id')) $bill->journal_entry_id = $jeId;
            }

            $date = $bill->vendor_bill_due_date ?? now();
            $ref  = (string)($bill->vendor_billID ?? $bill->id);
            $lines = [];

            foreach ($debitsByAccount as $accId => $amt) {
                if ($amt > 0) $lines[] = $this->line($jeId, $date, (int)$accId, $ref, 'Purchases (net)', $amt, 0);
            }
            if ($sumVAT > 0)  $lines[] = $this->line($jeId, $date, $accVATInput, $ref, 'VAT Input (recoverable)', $sumVAT, 0);
            if ($shipping > 0) $lines[] = $this->line($jeId, $date, $accFreight, $ref, 'Freight/Shipping charges', $shipping, 0);
            if ($addn > 0)     $lines[] = $this->line($jeId, $date, $accAdminExp, $ref, 'Additional charges', $addn, 0);

            if ($gross > 0)   $lines[] = $this->line($jeId, $date, $accAP, $ref, 'Accounts Payable', 0, $gross);

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            return $jeId;
        });
    }

    // --- helpers (same as PurchaseInvoicePosting) ---

    private function resolveLineDebitAccountId(object $li, int $companyId, int $fallbackAccId): int
    {
        if (property_exists($li, 'account_id') && $li->account_id) return (int)$li->account_id;
        if (property_exists($li, 'account_slug') && $li->account_slug) {
            $id = AccountHelper::id($li->account_slug, $companyId);
            if ($id) return (int)$id;
        }
        return $fallbackAccId;
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id   = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account not found for key/slug '$key' ('$slug') for company $companyId");
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
        $d=0; $c=0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Vendor Bill journal not balanced.');
    }
}
