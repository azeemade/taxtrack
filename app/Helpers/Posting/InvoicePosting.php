<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Posting\AccountHelper;

class InvoicePosting
{
    /**
     * Idempotent sales invoice posting built from line_items.
     *
     * Lines produced (NET sales workflow):
     *   Dr Accounts Receivable (invoice_value OR computed)
     *   Cr Sales revenue (sum of unit totals BEFORE discount)
     *   Dr Discounts (contra revenue) (sum of discounts)
     *   Cr VAT Payable (sum of VAT on net)
     *   Cr Shipping Income (shipping_charge) [optional]
     *   Cr Other Income (additional_charge) [optional]
     *   [Inventory items only]
     *     Dr COGS
     *     Cr Inventory
     *   [Optional rounding plug if UI total != computed]
     *     Dr/Cr Rounding/Suspense
     *
     * @param object   $invoice    Must contain: id, company_id, invoice_date?, invoiceID?, customer_id?, invoice_value?, shipping_charge?, additional_charge?, journal_entry_id?
     * @param int      $companyId
     * @param int|null $editedBy
     * @param int|null $quoteId    If provided, lines are pulled from Quote; otherwise from the invoice itself
     * @return int journal_entry_id
     * @throws \Throwable
     */
    public function syncInvoiceJournal(object $invoice, int $companyId, ?int $editedBy = null, ?int $quoteId = null): int
    {
        // ---------- 1) Pull line items ----------
        $linesQuery = DB::table('line_items');

        if ($quoteId) {
            $linesQuery->where('documentable_id', $quoteId)
                       ->where('documentable_type', 'App\Models\Quote');
        } else {
            $linesQuery->where('documentable_id', $invoice->id)
                       ->where('documentable_type', 'App\Models\Invoice');
        }

        $lines = $linesQuery->get([
            'id',
            'quantity',
            'price',
            'discount',
            'vat',
            'amount',         // UI gross (optional; used for consistency checks)
            'category_id',
            'cost_price',     // preferred cost]
        ]);

        if ($lines->isEmpty()) {
            throw new \RuntimeException('No line items found for invoice.');
        }

        // ---------- 2) Build totals from lines ----------
        $salesGross       = 0.0; // sum of unit totals BEFORE discount
        $discountTotal    = 0.0;
        $vatTotal         = 0.0;
        $grossFromLines   = 0.0; // sum of (net + vat) for all lines

        $cogsTotal        = 0.0; // only for inventory items

        foreach ($lines as $li) {
            $qty        = max((float)($li->quantity ?? 0), 0.0);
            $unitPrice  = (float)($li->price ?? 0);
            $unitTotal  = $qty * $unitPrice; // BEFORE discount

            $discVal    = (float)($li->discount ?? 0); // %/fraction/absolute handled below
            $vatVal     = (float)($li->vat ?? 0);      // %/fraction/absolute handled below
            $lineGross  = $li->amount !== null ? (float)$li->amount : null;   // UI gross (optional)

            $discountAmt = $this->discountAmount($unitTotal, $discVal);
            $netExVat    = max($unitTotal - $discountAmt, 0.0);
            $vatAmt      = $this->vatAmount($netExVat, $vatVal, $lineGross);

            $salesGross     += $unitTotal;
            $discountTotal  += $discountAmt;
            $vatTotal       += $vatAmt;
            $grossFromLines += ($netExVat + $vatAmt);

            // Inventory posting (COGS) only for inventory-type categories
            if ($this->isInventoryCategory((int)($li->category_id ?? 0))) {
                $unitCost = $this->resolveUnitCost($li);
                if ($unitCost > 0 && $qty > 0) {
                    $cogsTotal += ($unitCost * $qty);
                }
            }
        }

        $shipping     = (float)($invoice->shipping_charge ?? 0.0);
        $otherCharge  = (float)($invoice->additional_charge ?? 0.0);

        // AR from UI if present; else compute (most audit-friendly to use UI total, but we will guard with rounding plug)
        $computedTotal = $grossFromLines + $shipping + $otherCharge;
        $receivable    = (float)($invoice->invoice_value ?? $computedTotal);

        // ---------- 3) Resolve accounts ----------
        $accAR          = $this->accountIdFromKey('AR', $companyId);                   // trade-account-receivables
        $accSales       = $this->accountIdFromKey('SALES', $companyId);                // sales
        $accDiscounts   = $this->accountIdFromKey('DISCOUNTS', $companyId);            // discounts (contra)
        $accVATPayable  = $this->accountIdFromKey('VAT_PAYABLE', $companyId);          // vat-payable

        // Optional income buckets (fallback to SALES if not configured)
        $accShippingInc = $this->accountIdFromKeyOptional('SHIPPING_INCOME', $companyId) ?? $accSales;
        $accOtherInc    = $this->accountIdFromKeyOptional('OTHER_INCOME', $companyId)    ?? $accSales;

        // Inventory accounts (optional)
        $accCOGS        = $this->accountIdFromKeyOptional('COGS', $companyId);         // cost-of-goods-sold
        $accInventory   = $this->accountIdFromKeyOptional('INVENTORY', $companyId);    // inventories
        if (!$accInventory) {
            // Fallback by slug from your CoA template (inventories)
            $accInventory = $this->accountIdFromSlug('inventories', $companyId);
        }

        // Optional rounding/suspense account (else fallback to 'suspense-account')
        $accRounding    = $this->accountIdFromKeyOptional('ROUNDING_DIFF', $companyId)
                       ?? $this->accountIdFromSlug('suspense-account', $companyId);    // 1020020007

        // ---------- 4) Upsert header + lines ----------
        return DB::transaction(function () use (
            $invoice, $companyId, $editedBy, $receivable, $salesGross, $discountTotal, $vatTotal,
            $shipping, $otherCharge, $accAR, $accSales, $accDiscounts, $accVATPayable, $accShippingInc, $accOtherInc,
            $cogsTotal, $accCOGS, $accInventory, $accRounding, $computedTotal
        ) {
            // Header
            if (!empty($invoice->journal_entry_id)) {
                $jeId = (int)$invoice->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $invoice->invoice_date ?? now(),
                    'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'updated_at' => now(),
                    'type'       => 'original',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $invoice->invoice_date ?? now(),
                    'info'       => 'Invoice '.$this->safeStr($invoice->invoiceID).' for customer '.$this->safeStr($invoice->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => null,
                    'type'       => 'original',
                ]);

                DB::table('invoices')->where('id', $invoice->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($invoice, 'journal_entry_id')) $invoice->journal_entry_id = $jeId;
            }

            $date = $invoice->invoice_date ?? now();
            $ref  = $this->safeStr($invoice->invoiceID);

            $entries = [];

            // Dr AR (full receivable)
            if ($receivable > 0) {
                $entries[] = $this->line($jeId, $date, $accAR, $ref, 'Accounts Receivable', $receivable, 0);
            }

            // Cr Sales (gross BEFORE discount)
            if ($salesGross > 0) {
                $entries[] = $this->line($jeId, $date, $accSales, $ref, 'Sales revenue (gross)', 0, $salesGross);
            }

            // Dr Discounts (contra revenue)
            if ($discountTotal > 0) {
                $entries[] = $this->line($jeId, $date, $accDiscounts, $ref, 'Sales discounts', $discountTotal, 0);
            }

            // Cr VAT Payable
            if ($vatTotal > 0) {
                $entries[] = $this->line($jeId, $date, $accVATPayable, $ref, 'VAT on sales', 0, $vatTotal);
            }

            // Cr Shipping Income
            if ($shipping > 0) {
                $entries[] = $this->line($jeId, $date, $accShippingInc, $ref, 'Shipping charge', 0, $shipping);
            }

            // Cr Other Income
            if ($otherCharge > 0) {
                $entries[] = $this->line($jeId, $date, $accOtherInc, $ref, 'Additional charges', 0, $otherCharge);
            }

            // Inventory / COGS
            if ($cogsTotal > 0) {
                if (!$accCOGS || !$accInventory) {
                    Log::warning('COGS/Inventory posting skipped: account missing.', compact('accCOGS', 'accInventory'));
                } else {
                    $entries[] = $this->line($jeId, $date, $accCOGS,      $ref, 'Cost of goods sold', $cogsTotal, 0);
                    $entries[] = $this->line($jeId, $date, $accInventory, $ref, 'Reduce Inventory',  0, $cogsTotal);
                }
            }

            // Rounding / UI total reconciliation (guard)
            $diff = round($receivable - $computedTotal, 2);
            if (abs($diff) >= 0.01) {
                // If receivable > computed → credit rounding to keep debits=credits (and vice-versa)
                if ($diff > 0) {
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', 0, $diff);
                } else {
                    $entries[] = $this->line($jeId, $date, $accRounding, $ref, 'Rounding/Recon adjustment', -$diff, 0);
                }
            }

            // Persist all lines
            foreach ($entries as $e) {
                DB::table('finance_account_entries')->insert($e);
            }

            // Final balance guard
            $d = 0.0; $c = 0.0;
            foreach ($entries as $e) {
                $d += $e['debit_amount'];
                $c += $e['credit_amount'];
            }
            if (abs($d - $c) > 0.01) {
                Log::error("Invoice JE out of balance", ['debits' => $d, 'credits' => $c, 'invoice_id' => $invoice->id]);
                throw new \RuntimeException('Journal not balanced (debits != credits).');
            }

            return $jeId;
        });
    }

    // ---------------- helpers ----------------

    private function discountAmount(float $unitTotal, float $discountField): float
    {
        if ($discountField <= 0) return 0.0;
        // 0<x<=1 -> fraction; 1<x<=100 -> percent; else absolute
        if ($discountField > 0 && $discountField <= 1) {
            return round($unitTotal * $discountField, 2);
        } elseif ($discountField > 1 && $discountField <= 100) {
            return round($unitTotal * ($discountField / 100), 2);
        }
        return round(min($discountField, $unitTotal), 2);
    }

    private function vatAmount(float $netExVat, float $vatField, ?float $lineGross): float
    {
        if ($vatField <= 0) return 0.0;
        // 0<x<=1 -> fraction; 1<x<=100 -> percent; else absolute VAT
        if ($vatField > 0 && $vatField <= 1) {
            return round($netExVat * $vatField, 2);
        } elseif ($vatField > 1 && $vatField <= 100) {
            $calc = round($netExVat * ($vatField / 100), 2);
            // Prefer UI gross if consistent
            if ($lineGross && abs(($netExVat + $calc) - $lineGross) <= 0.01) return $calc;
            if ($lineGross && $lineGross > $netExVat) return round($lineGross - $netExVat, 2);
            return $calc;
        }
        // absolute VAT
        return round(max($vatField, 0.0), 2);
    }

    private function isInventoryCategory(int $categoryId): bool
    {
        if ($categoryId <= 0) return false;
        $slug = DB::table('categories')->where('id', $categoryId)->value('slug');
        if (!$slug) return false;

        // Override list via config if you like
        $inventorySlugs = config('inventory.inventory_slugs', [
            'hardware','materials','equipment','inventory','stock'
        ]);

        return in_array($slug, $inventorySlugs, true);
    }

    /**
     * Resolve a cost to use for COGS.
     * Priority: cost_price → product.price_cost? (example) → 0
     */
    private function resolveUnitCost(object $li): float
    {
        foreach (['cost_price', 'avg_cost'] as $col) {
            if (property_exists($li, $col) && $li->{$col} !== null) {
                $v = (float)$li->{$col};
                if ($v > 0) return $v;
            }
        }

        // Optional: product lookup if available
        if (property_exists($li, 'product_id') && $li->product_id) {
            $prod = DB::table('products')->where('id', $li->product_id)
                ->first(['cost_price', 'avg_cost', 'price_cost']);
            if ($prod) {
                foreach (['cost_price', 'avg_cost', 'price_cost'] as $col) {
                    if (property_exists($prod, $col) && $prod->{$col} !== null) {
                        $v = (float)$prod->{$col};
                        if ($v > 0) return $v;
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
        $debit  = round($debit, 2);
        $credit = round($credit, 2);

        return [
            'journal_entry_id' => $jeId,
            'date'             => $date instanceof Carbon ? $date : Carbon::parse($date),
            'account_id'       => $accountId,
            'reference'        => $ref,
            'description'      => $desc,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
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
