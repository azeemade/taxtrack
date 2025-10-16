<?php

namespace App\Helpers\Posting;

use App\Enums\DocumentableModelEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Helpers\Posting\AccountHelper;
use App\Models\Invoice;

class CreditNoteMultipleLinePosting
{
    public function syncForCreditNote(object $creditNote, int $companyId, ?int $editedBy = null, ?array $itemsFromRequest = null): int
    {
        $debugTag = "[CN#{$creditNote->id}]";

        Log::debug("$debugTag Start", [
            'company_id' => $companyId,
            'edited_by'  => $editedBy,
            'reference'  => $creditNote->referenceID ?? $creditNote->additional_referenceID ?? null,
            'issue_date' => (string)($creditNote->issue_date ?? now()),
        ]);

        // 1) Read pivot; join line context (need qty & cost for inventory reverse)
        $items = DB::table('credit_note_invoices as cni')
            ->join('line_items as li', 'li.id', '=', 'cni.line_item_id')
            ->join('invoices as inv', 'inv.id', '=', 'cni.invoice_id')
            ->leftJoin('categories as cat', 'cat.id', '=', 'li.category_id')
            ->where('cni.credit_note_id', $creditNote->id)
            ->get([
                'cni.id as cni_id',
                'cni.invoice_id',
                'cni.line_item_id',
                'cni.credit_amount_total as credit_amount',   // gross credited
                'li.amount  as line_gross',                   // gross per line (net+VAT)
                'li.vat     as line_vat_percent',             // percent (e.g., 23.00)
                'li.discount as line_discount',               // optional amount
                'li.quantity as line_qty',
                'li.cost_price as unit_cost',                 // cost for COGS reversal
                'li.category_id',
                'cat.slug as category_slug',
                'inv.journal_entry_id as invoice_journal_entry_id',
            ]);

        Log::debug("$debugTag Pivot", [
            'count' => $items->count(),
            'sum_credit_amount' => (float)$items->sum('credit_amount'),
        ]);

        // Fallback to payload if pivot empty
        if ($items->isEmpty() && !empty($itemsFromRequest)) {
            Log::debug("$debugTag Pivot empty; falling back to payload");
            $items = collect($itemsFromRequest)->map(function ($v) {
                $li = DB::table('line_items')
                    ->leftJoin('categories as cat', 'cat.id', '=', 'line_items.category_id')
                    ->where('line_items.id', $v['line_item_id'])
                    ->first([
                        'line_items.amount as line_gross',
                        'line_items.vat as line_vat_percent',
                        'line_items.discount as line_discount',
                        'line_items.quantity as line_qty',
                        'line_items.cost_price as unit_cost',
                        'line_items.category_id',
                        'cat.slug as category_slug',
                    ]);
                return (object)[
                    'cni_id'                  => null,
                    'invoice_id'              => (int)$v['invoice_id'],
                    'line_item_id'            => (int)$v['line_item_id'],
                    'credit_amount'           => (float)$v['credit_amount'],
                    'line_gross'              => $li ? (float)$li->line_gross      : null,
                    'line_vat_percent'        => $li ? (float)$li->line_vat_percent : null,
                    'line_discount'           => $li ? (float)$li->line_discount   : 0.0,
                    'line_qty'                => $li ? (float)$li->line_qty        : null,
                    'unit_cost'               => $li ? (float)$li->unit_cost       : null,
                    'category_id'             => $li ? (int)$li->category_id       : null,
                    'category_slug'           => $li ? (string)$li->category_slug  : null,
                    'invoice_journal_entry_id' => null,
                ];
            });
        }

        if ($items->isEmpty()) {
            Log::warning("$debugTag No credit items found.");
            throw new \RuntimeException('No credit items found for this credit note.');
        }

        // 2) Accounts
        $accAR         = $this->accountIdFromKey('AR', $companyId);
        $accSALES      = $this->accountIdFromKey('SALES', $companyId);
        $accVATPayable = $this->accountIdFromKey('VAT_PAYABLE', $companyId);
        $accDiscounts  = config('accounts.DISCOUNTS') ? $this->accountIdFromKey('DISCOUNTS', $companyId) : null;

        // Inventory accounts (optional, for inventory returns)
        $accCOGS      = $this->accountIdFromKeyOptional('COGS', $companyId);
        $accInventory = $this->accountIdFromKeyOptional('INVENTORY', $companyId);
        if (!$accInventory) {
            $accInventory = $this->accountIdFromSlugOptional('inventories', $companyId);
        }

        Log::debug("$debugTag Accounts", compact('accAR', 'accSALES', 'accVATPayable', 'accDiscounts', 'accCOGS', 'accInventory'));

        $hasQtyCol = Schema::hasColumn('credit_note_invoices', 'quantity_returned');

        // 3) Aggregate (sales/vat/discount) + derive quantities & inventory reversal
        $sumNet = 0.0;
        $sumVAT = 0.0;
        $sumDisc = 0.0;
        $sumTotal = 0.0;
        $sumInvDr = 0.0;
        $sumCogsCr = 0.0; // inventory reversal totals

        foreach ($items as $it) {
            $requested = max((float)$it->credit_amount, 0.0);

            // Remaining line gross cap (money)
            $remainingGross = $this->remainingLineGross((int)$it->line_item_id, (int)$it->invoice_id, (int)$creditNote->id);

            if ($requested > $remainingGross + 0.0001) {
                Log::warning("$debugTag Requested > remaining gross", [
                    'line_item_id' => $it->line_item_id,
                    'requested'    => $requested,
                    'remaining'    => $remainingGross,
                ]);
                $requested = $remainingGross; // clamp instead of throwing, to keep flow (optional)
            }

            // Split for sales/VAT/discount
            $split = $this->splitNetVatFromPercent(
                $requested,
                (float)($it->line_gross ?? 0),
                (float)($it->line_vat_percent ?? 0),
                (float)($it->line_discount ?? 0),
            );

            $sumNet   += $split['net'];
            $sumVAT   += $split['vat'];
            $sumDisc  += $split['discount'];
            $sumTotal += $requested;

            // ----- Derive quantity for inventory returns -----
            $isInventory = $this->isInventoryCategory((int)($it->category_id ?? 0), (string)($it->category_slug ?? ''));
            if ($isInventory) {
                $lineQty   = max((float)($it->line_qty ?? 0), 0.0);
                $lineGross = max((float)($it->line_gross ?? 0), 0.0);
                $unitCost  = max((float)($it->unit_cost ?? 0), 0.0);

                // unit gross used on the invoice
                $unitGross = ($lineQty > 0 && $lineGross > 0) ? ($lineGross / $lineQty) : 0.0;
                $qtyRequested = ($unitGross > 0) ? ($requested / $unitGross) : 0.0;

                // cap by remaining qty (original qty - prior returns)
                $qtyRemaining = $this->remainingLineQty((int)$it->line_item_id, (int)$it->invoice_id);
                $qtyToReturn  = min($qtyRequested, $qtyRemaining);
                $qtyToReturn  = max($qtyToReturn, 0.0);

                // value at cost for reversal
                $costAmt = $unitCost > 0 ? ($qtyToReturn * $unitCost) : 0.0;

                Log::debug("$debugTag Inventory derive", [
                    'line_item_id'  => $it->line_item_id,
                    'unit_gross'    => $this->m($unitGross),
                    'qty_requested' => $this->m($qtyRequested),
                    'qty_remaining' => $this->m($qtyRemaining),
                    'qty_to_return' => $this->m($qtyToReturn),
                    'unit_cost'     => $this->m($unitCost),
                    'cost_amt'      => $this->m($costAmt),
                ]);

                // write-back computed qty if column exists & this row came from pivot
                if ($hasQtyCol && !empty($it->cni_id)) {
                    DB::table('credit_note_invoices')
                        ->where('id', $it->cni_id)
                        ->update(['quantity_returned' => $qtyToReturn, 'updated_at' => now()]);
                }

                // accumulate inventory reversal
                $sumInvDr  += $costAmt; // Dr Inventory
                $sumCogsCr += $costAmt; // Cr COGS
            }
        }

        Log::debug("$debugTag Aggregates", [
            'sumNet'   => $this->m($sumNet),
            'sumVAT'   => $this->m($sumVAT),
            'sumDisc'  => $this->m($sumDisc),
            'sumTotal' => $this->m($sumTotal),
            'invDr'    => $this->m($sumInvDr),
            'cogsCr'   => $this->m($sumCogsCr),
        ]);

        if ($sumTotal <= 0.0) {
            Log::warning("$debugTag Zero total; aborting");
            throw new \RuntimeException('Credit Note has zero total — no lines to post.');
        }

        $parentJeId = optional($items->first())->invoice_journal_entry_id;

        // 4) Upsert header + lines
        return DB::transaction(function () use (
            $creditNote,
            $companyId,
            $editedBy,
            $accAR,
            $accSALES,
            $accVATPayable,
            $accDiscounts,
            $sumNet,
            $sumVAT,
            $sumDisc,
            $sumTotal,
            $sumInvDr,
            $sumCogsCr,
            $parentJeId,
            $debugTag,
            $accInventory,
            $accCOGS
        ) {
            // Header
            if (!empty($creditNote->journal_entry_id)) {
                $jeId = (int)$creditNote->journal_entry_id;
                Log::debug("$debugTag Update JE", ['journal_entry_id' => $jeId]);

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note ' . $this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? $creditNote->id) . ' for customer ' . $this->safeStr($creditNote->customer->company_name),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'type'       => 'sales_credit_note',
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $parentJeId,
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                Log::debug("$debugTag Create JE");
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note ' . $this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? $creditNote->id) . ' for customer ' . $this->safeStr($creditNote->customer->company_name),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'type'       => 'sales_credit_note',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $parentJeId,
                ]);

                DB::table('credit_notes')->where('id', $creditNote->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($creditNote, 'journal_entry_id')) $creditNote->journal_entry_id = $jeId;
            }

            $date      = $creditNote->issue_date ?? now();
            $reference = $this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? (string)$creditNote->id);

            $lines = [];
            if ($sumNet  > 0) $lines[] = $this->line($jeId, $date, $accSALES,      $reference, 'Reverse Sales (credit note)',    $sumNet, 0,   $editedBy);
            if ($sumVAT  > 0) $lines[] = $this->line($jeId, $date, $accVATPayable, $reference, 'Reverse VAT on sales',            $sumVAT, 0,   $editedBy);
            if ($accDiscounts && $sumDisc > 0)
                $lines[] = $this->line($jeId, $date, $accDiscounts,  $reference, 'Credit note discount/allowance', $sumDisc, 0,   $editedBy);
            if ($sumTotal > 0)  $lines[] = $this->line($jeId, $date, $accAR,         $reference, 'Reduce Accounts Receivable',     0,       $sumTotal, $editedBy);

            // Inventory reversal (only if accounts set and amount > 0)
            if ($sumInvDr > 0 && $sumCogsCr > 0 && $accInventory && $accCOGS) {
                $lines[] = $this->line($jeId, $date, $accInventory, $reference, 'Restock Inventory (credit note return)', $sumInvDr, 0,  $editedBy);
                $lines[] = $this->line($jeId, $date, $accCOGS,      $reference, 'Reverse COGS (credit note return)',      0,        $sumCogsCr, $editedBy);
            }

            Log::debug("$debugTag Lines prepared", ['count' => count($lines)]);

            DB::table('finance_account_entries')->insert($lines);

            $this->assertBalanced($lines);
            Log::debug("$debugTag Balanced OK");

            return $jeId;
        });
    }

    // ---- qty & gross caps ----

    private function remainingLineGross(int $lineItemId, int $invoiceId, ?int $excludeCreditNoteId = null): float
    {
        $gross = (float) DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $invoiceId)
            ->value('amount');

        $q = DB::table('credit_note_invoices')
            ->where('invoice_id', $invoiceId)
            ->where('line_item_id', $lineItemId);

        if ($excludeCreditNoteId) $q->where('credit_note_id', '!=', $excludeCreditNoteId);

        $alreadyCredited = (float) $q->sum('credit_amount_total');

        return max($gross - $alreadyCredited, 0.0);
    }

    private function remainingLineQty(int $lineItemId, int $invoiceId): float
    {
        $qty = (float) DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $invoiceId)
            ->value('quantity');

        // If quantity_returned column exists, use it; else assume no prior returns tracked (0)
        if (Schema::hasColumn('credit_note_invoices', 'quantity_returned')) {
            $returned = (float) DB::table('credit_note_invoices')
                ->where('invoice_id', $invoiceId)
                ->where('line_item_id', $lineItemId)
                ->sum('quantity_returned');
        } else {
            $returned = 0.0;
        }

        return max($qty - $returned, 0.0);
    }

    // ---- calc & utils ----

    private function splitNetVatFromPercent(float $creditGross, ?float $lineGross, ?float $lineVatPercent, float $lineDiscount = 0.0): array
    {
        $rate = $lineVatPercent !== null ? (float)$lineVatPercent : 0.0;
        if ($rate > 1.0) $rate = $rate / 100.0;

        $net = $rate > 0 ? ($creditGross / (1.0 + $rate)) : $creditGross;
        $vat = $creditGross - $net;

        $discount = 0.0;
        if ($lineDiscount > 0 && $lineGross && $lineGross > 0) {
            $p = min(max($creditGross / (float)$lineGross, 0.0), 1.0);
            $discount = (float)$lineDiscount * $p;
        }

        return ['net' => $net, 'vat' => $vat, 'discount' => $discount];
    }

    private function isInventoryCategory(int $categoryId, string $slug = ''): bool
    {
        if ($categoryId <= 0 && $slug === '') return false;
        $inventorySlugs = config('inventory.inventory_slugs', ['hardware', 'materials', 'equipment', 'inventory', 'stock']);
        if ($slug) return in_array($slug, $inventorySlugs, true);

        $rowSlug = DB::table('categories')->where('id', $categoryId)->value('slug');
        return $rowSlug ? in_array($rowSlug, $inventorySlugs, true) : false;
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit, ?int $editedBy = null): array
    {
        if (!$accountId) throw new \InvalidArgumentException("Missing account for {$desc}");
        $debit  = round($debit, 2);
        $credit = round($credit, 2);

        return [
            'journal_entry_id' => $jeId,
            'date'             => $date,
            'account_id'       => $accountId,
            'reference'        => $ref,
            'description'      => $desc,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
            'amount'           => round($debit - $credit, 2),
            'transaction_date' => $date,
            'edited_by'        => $editedBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    private function accountIdFromKey(string $key, int $companyId): int
    {
        $slug = config("accounts.$key");
        if (!$slug) throw new \InvalidArgumentException("Account slug not found for key '$key'");
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

    private function accountIdFromSlugOptional(string $slug, int $companyId): ?int
    {
        $id = AccountHelper::id($slug, $companyId);
        return $id ? (int)$id : null;
    }

    private function safeStr($v): string
    {
        return (string)($v ?? '');
    }
    private function m($n): string
    {
        return number_format((float)$n, 2, '.', '');
    }

    private function assertBalanced(array $lines): void
    {
        $d = 0.0;
        $c = 0.0;
        foreach ($lines as $l) {
            $d += $l['debit_amount'];
            $c += $l['credit_amount'];
        }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Credit Note journal not balanced.');
    }



    public static function recalcInvoiceBalance(int $invoiceId): void
    {
        $inv = DB::table('invoices')->where('id', $invoiceId)
            ->first(['invoice_value']);

        $paid = (float) DB::table('payment_records')
        ->where('recordable_type', DocumentableModelEnums::INVOICE->value)
        ->where('recordable_id', $invoiceId)
        ->sum('amount_paid');

        $credited = (float) DB::table('credit_note_invoices')
        ->where('invoice_id', $invoiceId)
        ->sum('credit_amount_total');

        $due = max(((float)$inv->invoice_value) - $paid - $credited, 0.0);

        $status = $due > 0 ? 'pending' : (($paid + $credited) >= (float)$inv->invoice_value ? 'paid' : 'pending');

        // DB::table('invoices')->where('id', $invoiceId)->update([
        //     // 'amount_due'     => round($due, 2), //amount_due column not found
        //     'payment_status' => $status,
        //     'updated_at'     => now(),
        // ]);
    }
}
