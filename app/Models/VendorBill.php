<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use App\Traits\PaymentRecordTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class VendorBill extends Model
{
    use HasFactory, PaymentRecordTrait, Companyable, SoftDeletes;

    protected $guarded = ['id'];
    protected $appends = ['amount_due', 'total_amount_paid'];
}
