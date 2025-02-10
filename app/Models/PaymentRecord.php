<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class PaymentRecord extends Model
{
    use HasFactory, Companyable, SoftDeletes;

    protected $guarded = ['id'];

    public function getAllowedActionsAttribute()
    {
        return ['delete', 'duplicate', 'remind'];
    }

    public function recordable()
    {
        return $this->morphTo('recordable', 'recordable_type');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
