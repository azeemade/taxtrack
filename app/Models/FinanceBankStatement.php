<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceBankStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_date',
        'referenceID',
        'description',
        'value_date',
        'withdrawals',
        'lodgments',
        'balance',
        'company_id',
        'created_by',
        'account_id',
    ];

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'account_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function reconciliationMatches()
    {
        return $this->hasMany(ReconciliationMatch::class, 'finance_bank_statement_id');
    }

    public function matchedEntries()
    {
        return $this->belongsToMany(FinanceAccountEntry::class, 'reconciliation_matches', 'finance_bank_statement_id', 'finance_account_entry_id')
            ->withPivot('matched_amount')
            ->withTimestamps();
    }
}
