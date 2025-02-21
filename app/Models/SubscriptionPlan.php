<?php

namespace App\Models;

use App\Traits\Subscription\SubscriptionPlanTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory, SubscriptionPlanTrait;
    protected $guarded = ['id'];

    public function subscriptionPlanFeature()
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'subscription_plan_id');
    }

    public function subscriptionFunctionality()
    {
        return $this->hasMany(SubscriptionFunctionality::class, 'subscription_plan_id');
    }

    public function subscriber()
    {
        return $this->hasMany(Subscriber::class, 'current_subscription_plan_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(SubscriptionHistory::class, 'subscription_plan_id');
    }
}
