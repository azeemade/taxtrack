<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountTransaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'account_id');
    }

    public function financeTransactionGroup()
    {
        return $this->belongsTo(FinanceAccountTransactionGroup::class, 'trans_group_id');
    }
}
