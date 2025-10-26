<?php

namespace App\Helpers\Posting;

use App\Enums\FinancialDocumentStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BillVoidPosting
{
    /**
     * Post a full reversal for a posted bill (only if no allocations/payments exist).
     *
     * Reversal JE mirrors original bill JE:
     *   Dr A/P (gross)
     *   Cr all expense lines (net)
     *   Cr VAT Input
     *   Cr Freight/Admin (if any)
     *
     * @param object $bill (expects: id, vendor_billID?, vendor_id?, company_id, journal_entry_id, status)
     * @param int $companyId
     * @param int|null $editedBy
     * @return int reversal_journal_entry_id
     */
    public function voidBill(object $bill, int $companyId, ?int $editedBy = null): int
    {
        // Guards
        $status = (string)($bill->status ?? '');
        if (!in_array($status, ['issued', 'published', 'ISSUED', 'PUBLISHED'], true)) {
            throw new \Exception('Only ISSUED bills can be voided.');
        }

        // No payments or debit notes allocated
        $alloc = (float) (DB::table('payment_records')->where('recordable_type', "App\Models\VendorBills")->where('recordable_id', $bill->id)->sum(DB::raw('amount_paid')) ?? 0);
        // $dnAlloc = (float) (DB::table('debit_notes')->where('bill_id', $bill->id)->sum('amount') ?? 0);
        if ($alloc > 0) //|| $dnAlloc > 0
        {
            throw new \Exception('Bill has settlements (payments/debit notes); cannot void. Create a reversing debit note instead.');
        }

        // Must have original JE to reverse
        $origJeId = (int) ($bill->journal_entry_id ?? 0);
        if ($origJeId <= 0) {
            throw new \Exception('Bill has no posted journal to void.');
        }

        // Fetch original lines
        $origLines = DB::table('finance_account_entries')
            ->where('journal_entry_id', $origJeId)
            ->get(['account_id', 'debit_amount', 'credit_amount', 'reference', 'description', 'date']);

        if ($origLines->isEmpty()) {
            throw new \Exception('Original bill journal has no lines.');
        }

        // Reverse: swap debits/credits per line
        return DB::transaction(function () use ($bill, $companyId, $editedBy, $origLines) {
            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'date'       => now(),
                'info'       => 'VOID Bill ' . $this->ref($bill->vendor_billID ?? $bill->id),
                'edited_by'  => $editedBy,
                'status'     => 'published',
                'created_at' => now(),
                'updated_at' => now(),
                'company_id' => (int)$companyId,
                'parent_journal_entry_id' => (int)($bill->journal_entry_id ?? null),
                'type'       => 'vendor_bill_void',
            ]);

            foreach ($origLines as $ol) {
                DB::table('finance_account_entries')->insert([
                    'journal_entry_id' => $jeId,
                    'date'             => now(),
                    'account_id'       => (int)$ol->account_id,
                    'reference'        => (string)($ol->reference ?? ''),
                    'description'      => 'VOID: ' . $this->ref($ol->description),
                    'debit_amount'     => round((float)$ol->credit_amount, 2), // swap
                    'credit_amount'    => round((float)$ol->debit_amount, 2),  // swap
                    'amount'           => round(((float)$ol->credit_amount - (float)$ol->debit_amount), 2),
                    'transaction_date' => now(),
                    'edited_by'        => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            // Finalize bill status
            DB::table('vendor_bills')->where('id', $bill->id)->update([
                'status'     =>  FinancialDocumentStatusEnums::VOID,
                'updated_at' => now(),
            ]);

            return $jeId;
        });
    }

    private function ref($v): string
    {
        return (string)($v ?? '');
    }
}
