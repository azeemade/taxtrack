<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class BudgetPeriod extends Model
{
    use HasFactory, Companyable, Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
        'year' => 'string',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetPeriod::class);
    }
}
