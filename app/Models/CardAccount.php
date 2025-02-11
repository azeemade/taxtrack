<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class CardAccount extends Model
{
    use HasFactory, Companyable, SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected function cvv(): Attribute
    {
        return Attribute::make(
            get: fn($value) => base64_decode($value),
            set: fn($value) => base64_encode($value)
        );
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function billingCountry()
    {
        return $this->belongsTo(Country::class, 'billing_country_id');
    }

    public function cardBrand()
    {
        return $this->belongsTo(CardBrand::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'issuing_bank_id');
    }

    public function paymentMethods()
    {
        return $this->morphMany(PaymentMethod::class, 'methodable');
    }

}
