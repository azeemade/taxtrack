<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class BudgetItem extends Model
{
    use HasFactory, Companyable, Auditable;

    protected $guarded = ['id'];
    protected $appends = ["budget_item_total"];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function periods()
    {
        return $this->hasMany(BudgetPeriod::class);
    }

    public function getBudgetItemTotalAttribute()
    {
        return $this->periods()->sum('amount');
    }

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class, 'account_id');
    }
}
