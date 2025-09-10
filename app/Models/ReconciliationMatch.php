<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationMatch extends Model
{
    protected $fillable = ['finance_bank_statement_id','finance_account_entry_id','matched_amount','created_by'];

    public function bankStatement()
    {
        return $this->belongsTo(FinanceBankStatement::class, 'finance_bank_statement_id');
    }

    public function accountEntry()
    {
        return $this->belongsTo(FinanceAccountEntry::class, 'finance_account_entry_id');
    }
}
