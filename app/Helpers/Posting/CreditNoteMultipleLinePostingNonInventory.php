<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\Posting\AccountHelper;

class CreditNoteMultipleLinePostingNonInventory
{

    //This works for perfectly non-inventory items
    public function syncForCreditNote(object $creditNote, int $companyId, ?int $editedBy = null, ?array $itemsFromRequest = null): int
    {
        $debugTag = "[CN#{$creditNote->id}]";

        Log::debug("$debugTag Starting CreditNoteMultipleLinePosting::syncForCreditNote", [
            'company_id' => $companyId,
            'edited_by'  => $editedBy,
            'reference'  => $creditNote->referenceID ?? $creditNote->additional_referenceID ?? null,
            'issue_date' => (string)($creditNote->issue_date ?? now()),
        ]);

        // 1) Try pivot
        $items = DB::table('credit_note_invoices as cni')
            ->join('line_items as li', 'li.id', '=', 'cni.line_item_id')
            ->join('invoices as inv', 'inv.id', '=', 'cni.invoice_id')
            ->where('cni.credit_note_id', $creditNote->id)
            ->get([
                'cni.invoice_id',
                'cni.line_item_id',
                'cni.credit_amount_total as credit_amount',
                'li.amount  as line_gross',
                'li.vat     as line_vat_percent',
                'li.discount as line_discount',
                'inv.journal_entry_id as invoice_journal_entry_id',
            ]);

        Log::debug("$debugTag Pivot fetch result", [
            'count' => $items->count(),
            'sum_credit_amount' => (float)$items->sum('credit_amount'),
            'rows' => $items->map(function ($r) {
                return [
                    'invoice_id'       => $r->invoice_id,
                    'line_item_id'     => $r->line_item_id,
                    'credit_amount'    => (float)$r->credit_amount,
                    'line_gross'       => (float)($r->line_gross ?? 0),
                    'line_vat_percent' => (float)($r->line_vat_percent ?? 0),
                    'line_discount'    => (float)($r->line_discount ?? 0),
                ];
            })->all(),
        ]);

        // 1b) Fallback to payload if pivot empty
        if ($items->isEmpty() && !empty($itemsFromRequest)) {
            Log::debug("$debugTag Pivot empty; falling back to payload", ['payload_items' => $itemsFromRequest]);

            $items = collect($itemsFromRequest)->map(function ($v) {
                $li = DB::table('line_items')
                    ->where('id', $v['line_item_id'])
                    ->first(['amount','vat','discount']);
                return (object)[
                    'invoice_id'               => (int)$v['invoice_id'],
                    'line_item_id'             => (int)$v['line_item_id'],
                    'credit_amount'            => (float)$v['credit_amount'],
                    'line_gross'               => $li ? (float)$li->amount   : null,
                    'line_vat_percent'         => $li ? (float)$li->vat      : null,
                    'line_discount'            => $li ? (float)$li->discount : 0.0,
                    'invoice_journal_entry_id' => null,
                ];
            });

            Log::debug("$debugTag Payload-derived items", [
                'count' => $items->count(),
                'sum_credit_amount' => (float)$items->sum('credit_amount'),
            ]);
        }

        if ($items->isEmpty()) {
            Log::warning("$debugTag No credit items found for this credit note.");
            throw new \RuntimeException('No credit items found for this credit note.');
        }

        // 2) Control accounts
        $accAR         = $this->accountIdFromKey('AR', $companyId);
        $accSALES      = $this->accountIdFromKey('SALES', $companyId);
        $accVATPayable = $this->accountIdFromKey('VAT_PAYABLE', $companyId);
        $accDiscounts  = config('accounts.DISCOUNTS') ? $this->accountIdFromKey('DISCOUNTS', $companyId) : null;

        Log::debug("$debugTag Control accounts", compact('accAR','accSALES','accVATPayable','accDiscounts'));

        // 3) Aggregate with guardrails
        $sumNet = 0.0; $sumVAT = 0.0; $sumDisc = 0.0; $sumTotal = 0.0;

        foreach ($items as $it) {
            $requested = (float)$it->credit_amount;
            $remaining = $this->remainingLineGross((int)$it->line_item_id, (int)$it->invoice_id, (int)$creditNote->id);

            Log::debug("$debugTag Line check", [
                'invoice_id'    => $it->invoice_id,
                'line_item_id'  => $it->line_item_id,
                'requested'     => $this->m($requested),
                'remaining_cap' => $this->m($remaining),
                'line_gross'    => $this->m((float)($it->line_gross ?? 0)),
                'vat_percent'   => (float)($it->line_vat_percent ?? 0),
                'discount'      => $this->m((float)($it->line_discount ?? 0)),
            ]);

            if ($requested > $remaining + 0.0001) {
                Log::warning("$debugTag Requested exceeds remaining", [
                    'requested' => $requested, 'remaining' => $remaining
                ]);
                throw new \BadMethodCallException("Credit exceeds remaining on line {$it->line_item_id}. Remaining: {$remaining}");
            }

            $split = $this->splitNetVatFromPercent(
                $requested,
                (float)($it->line_gross ?? 0),
                (float)($it->line_vat_percent ?? 0),
                (float)($it->line_discount ?? 0),
            );

            Log::debug("$debugTag Split result", [
                'net'      => $this->m($split['net']),
                'vat'      => $this->m($split['vat']),
                'discount' => $this->m($split['discount']),
            ]);

            $sumNet   += $split['net'];
            $sumVAT   += $split['vat'];
            $sumDisc  += $split['discount'];
            $sumTotal += $requested;
        }

        // clamps
        $sumNet   = max($sumNet, 0.0);
        $sumVAT   = max($sumVAT, 0.0);
        $sumDisc  = max($sumDisc, 0.0);
        $sumTotal = max($sumTotal, 0.0);

        Log::debug("$debugTag Aggregates", [
            'sumNet'   => $this->m($sumNet),
            'sumVAT'   => $this->m($sumVAT),
            'sumDisc'  => $this->m($sumDisc),
            'sumTotal' => $this->m($sumTotal),
        ]);

        if ($sumTotal <= 0.0) {
            Log::warning("$debugTag Zero total after aggregation; aborting post.");
            throw new \RuntimeException('Credit Note has zero total — no lines to post.');
        }

        $parentJeId = optional($items->first())->invoice_journal_entry_id;

        return DB::transaction(function () use (
            $creditNote, $companyId, $editedBy,
            $accAR, $accSALES, $accVATPayable, $accDiscounts,
            $sumNet, $sumVAT, $sumDisc, $sumTotal, $parentJeId, $debugTag
        ) {
            // Header upsert
            if (!empty($creditNote->journal_entry_id)) {
                $jeId = (int)$creditNote->journal_entry_id;
                Log::debug("$debugTag Updating existing JE", ['journal_entry_id' => $jeId]);

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note '.$this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? $creditNote->id).' for customer '.$this->safeStr($creditNote->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'type'       => 'sales_credit_note',
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $parentJeId,
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                Log::debug("$debugTag Creating new JE");
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $creditNote->issue_date ?? now(),
                    'info'       => 'Credit Note '.$this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? $creditNote->id).' for customer '.$this->safeStr($creditNote->customer_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'published',
                    'type'       => 'sales_credit_note',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $parentJeId,
                    'company_id' => $companyId,
                ]);

                DB::table('credit_notes')->where('id', $creditNote->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($creditNote, 'journal_entry_id')) {
                    $creditNote->journal_entry_id = $jeId;
                }
                Log::debug("$debugTag New JE created", ['journal_entry_id' => $jeId]);
            }

            // Lines
            $date      = $creditNote->issue_date ?? now();
            $reference = $this->safeStr($creditNote->referenceID ?? $creditNote->additional_referenceID ?? (string)$creditNote->id);

            $lines = [];
            if ($sumNet  > 0) $lines[] = $this->line($jeId, $date, $companyId, $accSALES,      $reference, 'Reverse Sales (credit note)',    $sumNet, 0,   $editedBy);
            if ($sumVAT  > 0) $lines[] = $this->line($jeId, $date, $companyId, $accVATPayable, $reference, 'Reverse VAT on sales',            $sumVAT, 0,   $editedBy);
            if ($accDiscounts && $sumDisc > 0)
                               $lines[] = $this->line($jeId, $date, $companyId, $accDiscounts,  $reference, 'Credit note discount/allowance', $sumDisc, 0,   $editedBy);
            if ($sumTotal> 0) $lines[] = $this->line($jeId, $date, $companyId, $accAR,         $reference, 'Reduce Accounts Receivable',     0,       $sumTotal, $editedBy);

            Log::debug("$debugTag Prepared lines", [
                'count' => count($lines),
                'preview' => array_map(function ($l) { return [
                    'account_id'    => $l['account_id'],
                    'debit_amount'  => $this->m($l['debit_amount']),
                    'credit_amount' => $this->m($l['credit_amount']),
                ]; }, $lines),
            ]);

            if (empty($lines)) {
                Log::error("$debugTag No account lines generated — this should not happen.");
                throw new \RuntimeException('No account lines generated for credit note.');
            }

            DB::table('finance_account_entries')->insert($lines);
            Log::debug("$debugTag Inserted account lines", ['inserted' => count($lines)]);

            $this->assertBalanced($lines);
            Log::debug("$debugTag Balanced OK");

            return $jeId;
        });
    }

    // -------- Helpers --------

    private function remainingLineGross(int $lineItemId, int $invoiceId, ?int $excludeCreditNoteId = null): float
    {
        $gross = (float) DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $invoiceId)
            ->value('amount');

        $q = DB::table('credit_note_invoices')
            ->where('invoice_id', $invoiceId)
            ->where('line_item_id', $lineItemId);

        if ($excludeCreditNoteId) {
            $q->where('credit_note_id', '!=', $excludeCreditNoteId);
        }

        $alreadyCredited = (float) $q->sum('credit_amount_total');

        Log::debug("[remainingLineGross] line_item_id=$lineItemId invoice_id=$invoiceId", [
            'gross' => $this->m($gross),
            'already_credited' => $this->m($alreadyCredited),
            'remaining' => $this->m(max($gross - $alreadyCredited, 0.0)),
        ]);

        return max($gross - $alreadyCredited, 0.0);
    }

    private function splitNetVatFromPercent(
        float $creditGross,
        ?float $lineGross,
        ?float $lineVatPercent,
        float $lineDiscount = 0.0
    ): array {
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

    private function line(int $jeId, $date, int $companyId, int $accountId, string $ref, string $desc, float $debit, float $credit, ?int $editedBy = null): array
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
            'edited_by'        => $editedBy,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    private function accountIdFromKey(string $key, int $companyId): int
    {
        $slug = config("accounts.{$key}");
        if (!$slug) throw new \InvalidArgumentException("Account slug not found for key '{$key}' in config/accounts.php");
        $id = AccountHelper::id($slug, $companyId);
        if (!$id) throw new \RuntimeException("Account with slug '{$slug}' not found for company {$companyId}");
        return (int)$id;
    }

    private function safeStr($value): string
    {
        return (string) ($value ?? '');
    }

    private function m($n): string
    {
        return number_format((float)$n, 2, '.', '');
    }

    private function assertBalanced(array $lines): void
    {
        $d=0.0; $c=0.0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Credit Note journal not balanced.');
    }
}
