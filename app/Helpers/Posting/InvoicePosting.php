<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicePosting
{
    /**
     * Idempotent sales invoice posting built from line_items.
     *
     * Lines produced:
     *   Dr Accounts Receivable (invoice_value OR computed)
     *   Cr Sales revenue (sum of unit totals BEFORE discount)
     *   Dr Discounts (contra revenue) (sum of discounts)
     *   Cr VAT Payable (sum of VAT on net)
     *   Cr Shipping Income (shipping_charge)
     *   Cr Other Income (additional_charge)
     *   [Inventory items only]
     *     Dr COGS
     *     Cr Inventory
     */
    public function syncInvoiceJournal(object $invoice, int $companyId, ?int $editedBy = null): int
    {
        // ------------- 1) Pull line items -------------
        $lines = DB::table('line_items')
            ->where('documentable_id', $invoice->id)
            ->where('documentable_type', 'invoices')
            ->get([
                'id','quantity','price','total_unit_price','discount','vat','amount','category_id',
                // optional cost columns if you have them:
                'unit_cost','cost_price','cost','average_cost',
                // optional product pointer:
                'product_id',
            ]);

        if ($lines->isEmpty()) {
            throw new \RuntimeException('No line items found for invoice.');
        }

        // ------------- 2) Build totals from lines -------------
        $salesGross       = 0.0; // sum of unit totals BEFORE discount
        $discountTotal    = 0.0;
        $vatTotal         = 0.0;
        $grossFromLines   = 0.0; // sum of line gross (net + vat)

        $cogsTotal        = 0.0; // only for inventory items
        $inventoryTotal   = 0.0;

        foreach ($lines as $li) {
            $qty        = max((float)($li->quantity ?? 0), 0.0);
            $unitPrice  = (float)($li->price ?? 0);
            $unitTotal  = $this->orFloat($li->total_unit_price, $qty * $unitPrice); // BEFORE discount
            $discVal    = (float)($li->discount ?? 0); // may be % or absolute
            $vatVal     = (float)($li->vat ?? 0);      // may be % or absolute (system uses % on sales)
            $lineGross  = (float)($li->amount ?? 0);   // usually net+vat, from UI

            $discountAmt = $this->discountAmount($unitTotal, $discVal);
            $netExVat    = max($unitTotal - $discountAmt, 0.0);
            $vatAmt      = $this->vatAmount($netExVat, $vatVal, $lineGross);

            $salesGross     += $unitTotal;
            $discountTotal  += $discountAmt;
            $vatTotal       += $vatAmt;
            $grossFromLines += ($netExVat + $vatAmt);

            // Inventory posting (COGS/Inventory) only for inventory categories
            if ($this->isInventoryCategory((int)($li->category_id ?? 0))) {
                $unitCost = $this->resolveUnitCost($li);
                if ($unitCost > 0 && $qty > 0) {
                    $cost = $unitCost * $qty;
                    $cogsTotal      += $cost;
                    $inventoryTotal += $cost;
                }
            }
        }

        $shipping     = (float)($invoice->shipping_charge ?? 0.0);
        $otherCharge  = (float)($invoice->additional_charge ?? 0.0);

        // Prefer persisted invoice_value for AR (UI truth); else compute.
        $receivable = (float)($invoice->invoice_value ?? ($grossFromLines + $shipping + $otherCharge));

        // ------------- 3) Resolve accounts -------------
        $accAR          = $this->accountIdFromKey('AR', $companyId);
        $accSales       = $this->accountIdFromKey('SALES', $companyId);
        $accDiscounts   = $this->accountIdFromKey('DISCOUNTS', $companyId);
        $accVATPayable  = $this->accountIdFromKey('VAT_PAYABLE', $companyId);

        // Optional income buckets (fallback to SALES if not configured)
        $accShippingInc = $this->accountIdFromKeyOptional('SHIPPING_INCOME', $companyId) ?? $accSales;
        $accOtherInc    = $this->accountIdFromKeyOptional('OTHER_INCOME', $companyId) ?? $accSales;

        // Inventory accounts
        $accCOGS        = $this->accountIdFromKeyOptional('COGS', $companyId);         // 'cost-of-goods-sold'
        $accInventory   = $this->accountIdFromKeyOptional('INVENTORY', $companyId)     // 'inventories'
                          ?? $this->accountIdFromSlug('inventories', $companyId);      // fallback by slug present in your CoA

        // ------------- 4) Upsert header + lines -------------
        return DB::transaction(function () use (
            $invoice, $companyId, $editedBy, $receivable, $salesGross, $discountTotal, $vatTotal,
            $shipping, $otherCharge, $accAR, $accSales, $accDiscounts, $accVATPayable, $accShippingInc, $accOtherInc,
            $cogsTotal, $accCOGS, $accInventory
        ) {
            // Header
            if (!empty($invoice->journal_entry_id)) {
                $jeId = (int)$invoice->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $invoice->invoice_date ?? now(),
                    'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => 'sales_invoice',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $invoice->invoice_date ?? now(),
                    'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'sales_invoice',
                ]);

                DB::table('invoices')->where('id', $invoice->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($invoice, 'journal_entry_id')) $invoice->journal_entry_id = $jeId;
            }

            $date = $invoice->invoice_date ?? now();
            $ref  = $this->safeStr($invoice->invoiceID);

            $entries = [];

            // Dr AR
            if ($receivable > 0) $entries[] = $this->line($jeId, $date, $accAR, $ref, 'Accounts Receivable', $receivable, 0);

            // Cr Sales (gross before discount)
            if ($salesGross > 0) $entries[] = $this->line($jeId, $date, $accSales, $ref, 'Sales revenue (gross)', 0, $salesGross);

            // Dr Discounts (contra)
            if ($discountTotal > 0) $entries[] = $this->line($jeId, $date, $accDiscounts, $ref, 'Sales discounts', $discountTotal, 0);

            // Cr VAT Payable
            if ($vatTotal > 0) $entries[] = $this->line($jeId, $date, $accVATPayable, $ref, 'VAT on sales', 0, $vatTotal);

            // Cr Shipping Income
            if ($shipping > 0) $entries[] = $this->line($jeId, $date, $accShippingInc, $ref, 'Shipping charge', 0, $shipping);

            // Cr Other Income
            if ($otherCharge > 0) $entries[] = $this->line($jeId, $date, $accOtherInc, $ref, 'Additional charges', 0, $otherCharge);

            // Inventory/COGS (only when amounts exist & accounts resolved)
            if ($cogsTotal > 0) {
                if (!$accCOGS || !$accInventory) {
                    Log::warning('COGS/Inventory posting skipped: account missing.', compact('accCOGS','accInventory'));
                } else {
                    $entries[] = $this->line($jeId, $date, $accCOGS,      $ref, 'Cost of goods sold', $cogsTotal, 0);
                    $entries[] = $this->line($jeId, $date, $accInventory, $ref, 'Reduce Inventory', 0, $cogsTotal);
                }
            }

            foreach ($entries as $e) DB::table('finance_account_entries')->insert($e);

            // Balance guard
            $d=0.0; $c=0.0;
            foreach ($entries as $e) { $d += $e['debit_amount']; $c += $e['credit_amount']; }
            if (abs($d - $c) > 0.01) { // small tolerance
                Log::error("Invoice JE out of balance", ['debits'=>$d,'credits'=>$c,'invoice_id'=>$invoice->id]);
                throw new \RuntimeException('Journal not balanced (debits != credits).');
            }

            return $jeId;
        });
    }

    // ---------------- helpers ----------------

    private function discountAmount(float $unitTotal, float $discountField): float
    {
        if ($discountField <= 0) return 0.0;
        // Treat 0 < x <= 1 as fraction; 1 < x <= 100 as percent; else absolute
        if ($discountField > 0 && $discountField <= 1) {
            return round($unitTotal * $discountField, 2);
        } elseif ($discountField > 1 && $discountField <= 100) {
            return round($unitTotal * ($discountField / 100), 2);
        }
        return min($discountField, $unitTotal);
    }

    private function vatAmount(float $netExVat, float $vatField, ?float $lineGross): float
    {
        if ($vatField <= 0) return 0.0;
        // 0<x<=1 -> fraction, 1<x<=100 -> percent, else assume absolute VAT
        if ($vatField > 0 && $vatField <= 1) {
            return round($netExVat * $vatField, 2);
        } elseif ($vatField > 1 && $vatField <= 100) {
            $calc = round($netExVat * ($vatField / 100), 2);
            // If UI sent 'amount' (gross), prefer it when consistent
            if ($lineGross && abs(($netExVat + $calc) - $lineGross) <= 0.01) return $calc;
            if ($lineGross && $lineGross > $netExVat) return round($lineGross - $netExVat, 2);
            return $calc;
        }
        // treat as absolute VAT amount
        return max($vatField, 0.0);
    }

    private function isInventoryCategory(int $categoryId): bool
    {
        if ($categoryId <= 0) return false;
        $slug = DB::table('categories')->where('id', $categoryId)->value('slug');
        if (!$slug) return false;

        // Allow config override: config('inventory.inventory_slugs', [...])
        $inventorySlugs = config('inventory.inventory_slugs', [
            'hardware','materials','equipment','inventory','stock'
        ]);

        return in_array($slug, $inventorySlugs, true);
    }

    private function resolveUnitCost(object $li): float
    {
        // Prefer explicit unit cost columns if present on line
        foreach (['unit_cost','cost_price','average_cost','cost'] as $col) {
            if (property_exists($li, $col) && $li->{$col} !== null) {
                return (float)$li->{$col};
            }
        }
        // Optional: pull from products table if you store it there
        if (property_exists($li, 'product_id') && $li->product_id) {
            $prod = DB::table('products')->where('id', $li->product_id)
                ->first(['unit_cost','cost_price','average_cost','cost']);
            if ($prod) {
                foreach (['unit_cost','cost_price','average_cost','cost'] as $col) {
                    if (property_exists($prod, $col) && $prod->{$col} !== null) {
                        return (float)$prod->{$col};
                    }
                }
            }
        }
        return 0.0; // no cost info → skip COGS/Inventory posting
    }

    private function accountIdFromKey(string $key, int $companyId): int
    {
        $slug = config("accounts.$key");
        if (!$slug) throw new \InvalidArgumentException("Account slug not found for key '$key' in config/accounts.php");
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account with slug '$slug' not found for company $companyId");
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
        if (!$accountId) {
            Log::error("Missing account for $desc");
            throw new \InvalidArgumentException("Missing account for $desc");
        }
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

    private function orFloat($value, float $fallback): float
    {
        return $value !== null ? (float)$value : $fallback;
    }

    private function safeStr($v): string { return (string)($v ?? ''); }
}
