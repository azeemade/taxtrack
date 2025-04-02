<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function accountSubCategories()
    {
        return $this->hasMany(FinanceAccountSubCategory::class, 'account_category_id');
    }

    public function accounts()
    {
        return $this->hasMany(FinanceChartOfAccount::class, 'account_category_id');
    }

    public function accountType()
    {
        return $this->belongsTo(FinanceAccountType::class, 'account_type_id');
    }
}
