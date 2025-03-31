<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceChartOfAccount extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $with = ['user'];

    public function financeTransactions()
    {
        return $this->hasMany(FinanceAccountTransaction::class);
    }
 
    public function subCategory()
    {
        return $this->belongsTo(FinanceAccountSubCategory::class, 'account_sub_category_id');
    }

    public function accountCategory()
    {
        return $this->belongsTo(FinanceAccountCategory::class, 'account_category_id');
    }

    public function accountType()
    {
        return $this->belongsTo(FinanceAccountType::class, 'account_type_id');
    }
    
    public function accountEntries()
    {
        return $this->hasMany(FinanceAccountEntry::class, 'account_id');
    }
    
    public function fromAccount()
    {
        return $this->hasMany(FinanceAccountTransfer::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->hasMany(FinanceAccountTransfer::class, 'to_account_id');
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'account_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
