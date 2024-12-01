<?php

namespace App\Traits;

use App\Models\PaymentRecord;

trait PaymentRecordTrait
{
    protected $appends = ['amount_due', 'total_amount_paid'];
    public function paymentRecords()
    {
        return $this->morphMany(PaymentRecord::class, 'recordable');
    }

    public function latestPaymentRecord()
    {
        return $this->hasOne(PaymentRecord::class, 'recordable_id')->latestOfMany();
    }

    public function getAmountDueAttribute()
    {
        return $this->latestPaymentRecord->amount_due ?? $this->{$this->total_amount};
    }

    public function getTotalAmountPaidAttribute()
    {
        return $this->paymentRecords->sum('amount_paid') ?? 0.00;
    }
}
