<?php

namespace App\Traits\Subscription;

use App\Services\ThirdPartyApi\Stripe\Stripe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait PaymentProviderSubscriberTrait
{

    protected static function bootSubscriberTrait()
    {
        $stripe = new Stripe();

        static::created(function (Model $model) use ($stripe) {
            $customer = $stripe->createCustomer([
                'email' => $model->company->email,
                'name' => $model->company->name,
                'metadata' => [
                    'company_id' => $model->company->companyUUID
                ]
            ]);

            $model->update([
                'provider_customer_id' => $customer->id
            ]);
        });
    }
}
