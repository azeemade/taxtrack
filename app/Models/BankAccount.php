<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class BankAccount extends Model
{
    use HasFactory, Companyable, SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = [
        'is_active' => 'boolean'
    ];
    protected $hidden = ['userID', 'user_password', 'connected'];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentMethods()
    {
        return $this->morphMany(PaymentMethod::class, 'methodable');
    }
}
