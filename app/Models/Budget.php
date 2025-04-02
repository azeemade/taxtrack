<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class Budget extends Model
{
    use HasFactory, Companyable, Auditable;

    protected $guarded = ['id'];
    protected $appends = ["budget_total"];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budgetItems()
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function getBudgetTotalAttribute()
    {
        return $this->budgetItems()->withSum('periods', 'amount')->get()->sum('budget_item_total');
    }
}
