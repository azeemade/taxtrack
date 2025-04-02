<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceJournalEntry extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function accountEntries()
    {
        return $this->hasMany(FinanceAccountEntry::class, 'journal_entry_id');
    }

    public function accountTransfer()
    {
        return $this->hasMany(FinanceAccountTransfer::class);
    }

    public function financeAccountTransaction()
    {
        return $this->hasMany(FinanceAccountTransaction::class);
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
