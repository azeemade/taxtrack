<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    public function subscriptionPlan()
    {
        return $this->hasMany(SubscriptionPlan::class, 'current_subscription_plan_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
