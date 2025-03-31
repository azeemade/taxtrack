<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountSubCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function accounts()
    {
        return $this->hasMany(FinanceChartOfAccount::class, 'account_sub_category_id');
    }

    public function accountCategory()
    {
        return $this->belongsTo(FinanceAccountCategory::class, 'account_category_id');
    }

    public function accountType()
    {
        return $this->belongsTo(FinanceAccountType::class, 'account_type_id');
    }
}
