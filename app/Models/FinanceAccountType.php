<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountType extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function accountCategories()
    {
        return $this->hasMany(FinanceAccountCategory::class, 'account_type_id');
    }

    public function accountSubCategories()
    {
        return $this->hasMany(FinanceAccountSubCategory::class, 'account_type_id');
    }

    public function accounts()
    {
        return $this->hasMany(FinanceChartOfAccount::class, 'account_type_id');
    }
}
