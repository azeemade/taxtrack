<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SubscriptionHistory extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function scopeSubscriber($query)
    {
        $currentUser = Auth::user();
        if (!$currentUser?->hasRole(['client'])) {
            return $query;
        }
        return $query->whereRelation('subscriber', 'company_id', $currentUser?->company?->id)
            ->whereRelation('subscriber', 'user_id', $currentUser?->id);
    }

    public function subscriptionRefund()
    {
        return $this->hasOne(SubscriptionRefund::class, 'subscription_history_id');
    }

    public function subscriptionCancellation()
    {
        return $this->hasOne(SubscriptionCancellation::class, 'subscription_history_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }
}
