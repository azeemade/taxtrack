<?php

namespace App\Traits\Subscription;

use App\Services\ThirdPartyApi\Stripe\Stripe;
use Illuminate\Database\Eloquent\Model;

trait SubscriptionPlanTrait
{

    protected static function bootSubscriptionPlanTrait()
    {
        $stripe = new Stripe();

        static::created(function (Model $model) use ($stripe) {
            $product = $stripe->createCustomer([
                'email' => $model->user->email,
                'name' => $model->name,
            ]);

            $model->update([
                'stripe_customerID' => $product->id
            ]);
        });
    }
}
