<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionCancellation extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected function reason(): Attribute
    {
        return Attribute::make(
            get: fn($value) => unserialize($value),
            set: fn($value) => serialize($value)
        );
    }

    public function subscriptionHistory()
    {
        return $this->belongsTo(SubscriptionHistory::class, 'subscription_history_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }
}
