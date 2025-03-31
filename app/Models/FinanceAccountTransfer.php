<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountTransfer extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'account_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(FinanceJournalEntry::class, 'journal_entry_id');
    }

    public function fromAccount()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'to_account_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
