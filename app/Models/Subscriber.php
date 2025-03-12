<?php

namespace App\Models;

use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use App\Traits\Subscription\PaymentProviderSubscriberTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model

{
    use HasFactory, PaymentProviderSubscriberTrait, Companyable, Auditable;

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'current_subscription_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionHistory()
    {
        return $this->hasMany(SubscriptionHistory::class, 'subscriber_id');
    }

    public function currentSubscriptionHistory()
    {
        return $this->hasOne(SubscriptionHistory::class, 'subscriber_id')->latest();
    }

    // public function currentPlan()
    // {
    //     return $this->belongsTo(SubscriptionPlan::class, 'current_subscription_plan_id');
    // }
}
