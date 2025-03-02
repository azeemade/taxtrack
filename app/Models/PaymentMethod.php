<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class PaymentMethod extends Model
{
    use HasFactory, Companyable, SoftDeletes;

    protected $guarded = ['id'];
    protected $appends = ['name'];

    public function getNameAttribute()
    {
        if ($this->methodable_type === 'App\Models\CardAccount') {
            return $this->methodable->cardBrand->name ?? null;
        }
        return  $this->methodable->bank->name ?? null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function methodable()
    {
        return $this->morphTo();
    }
}
