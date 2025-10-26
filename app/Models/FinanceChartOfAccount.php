<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([ModelUserScope::class])]
class FinanceChartOfAccount extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];
    protected $with = ['editedBy:id,name,email'];

    protected function setUniqueKeys()
    {
        $this->uniqueKeys = [
            ['company_id', 'account_number']
        ];
    }
    
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


    public function toArray()
    {
        $data = parent::toArray();

        if (!empty($data['edited_by']) && is_array($data['edited_by'])) {
            unset(
                $data['edited_by']['roles'],
                $data['edited_by']['user_permissions'],
                $data['edited_by']['user_permissions_count'],
                $data['edited_by']['permissions']
            );
        }

        if (!empty($data['account_entries'])) {
            unset($data['account_entries']);
        }

        return $data;
    }
}
