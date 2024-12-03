<?php

namespace App\Models;

use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    use HasFactory, Companyable;

    protected $guarded = ['id'];

    public function recordable()
    {
        return $this->morphTo('recordable', 'recordable_type');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
