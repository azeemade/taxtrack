<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountEntry extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function journalEntry()
    {
        return $this->belongsTo(FinanceJournalEntry::class, 'journal_entry_id');
    }

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'account_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function reconciliationMatches()
    {
        return $this->hasMany(ReconciliationMatch::class, 'finance_account_entry_id');
    }

    public function matchedBankStatements()
    {
        return $this->belongsToMany(FinanceBankStatement::class, 'reconciliation_matches', 'finance_account_entry_id', 'finance_bank_statement_id')
            ->withPivot('matched_amount')
            ->withTimestamps();
    }
}
