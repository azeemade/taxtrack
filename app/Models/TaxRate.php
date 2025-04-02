<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class TaxRate extends Model
{
    use HasFactory, SoftDeletes, Companyable, Auditable;
    protected $guarded = ['id'];

    protected $casts = [
        'non_recoverable' => 'boolean',
        'has_components' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getAllowedActionsAttribute()
    {
        return ['delete', 'toggle'];
    }

    public function components()
    {
        return $this->hasMany(TaxRateComponent::class);
    }
}
