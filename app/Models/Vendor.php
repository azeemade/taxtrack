<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use App\Traits\ContactPersonTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class Vendor extends Model
{
    use HasFactory, ContactPersonTrait, Companyable, SoftDeletes;
    protected $guarded = ['id'];
    protected $appends = ['outstanding_bills', 'payable_bills'];
    protected $casts = ["is_active" => "boolean"];
    protected $append = ['allowed_actions'];

    public function getAllowedActionsAttribute()
    {
        return ['toggle', 'delete'];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendorBills()
    {
        return $this->hasMany(VendorBill::class);
    }

    public function latestPaymentRecord()
    {
        return $this->hasOne(VendorBill::class)->latestOfMany();
    }

    public function getOutstandingBillsAttribute()
    {
        return $this->vendorBills->reduce(function ($carry, $item) {
            return $carry + $item->latestPaymentRecord?->amount_due ?? $this->{$item->vendor_bill_total};
        }, 0);
    }

    public function getPayableBillsAttribute()
    {
        return $this->vendorBills->sum('vendor_bill_total') ?? 0.00;
    }
}
