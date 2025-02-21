<?php

namespace App\Traits\Subscription;

use App\Constants\SubscriptionConstant;
use App\Services\ThirdPartyApi\Stripe\Stripe;
use Illuminate\Database\Eloquent\Model;

trait SubscriptionPlanTrait
{

    protected static function bootSubscriptionPlanTrait()
    {
        $stripe = new Stripe();

        static::created(function (Model $model) use ($stripe) {
            $product = $stripe->createProduct([
                'name' => $model->title,
                'description' => $model->short_description,
                'active' => $model->is_active
            ]);

            $this->modifyStripePrice($model, $stripe);

            $model->update([
                'stripe_productID' => $product->id
            ]);
        });

        static::updated(function (Model $model) use ($stripe) {
            $stripe->updateProduct(
                $model->stripe_productID,
                [
                    'name' => $model->title,
                    'description' => $model->short_description,
                    'active' => $model->is_active
                ]
            );

            $this->modifyStripePrice($model, $stripe);
        });

        static::deleted(function (Model $model) use ($stripe) {
            $stripe->deleteProduct($model->stripe_productID);
        });
    }

    protected function modifyStripePrice(
        Model $model,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe
    ) {
        if ($model->stripe_priceID) {
            $monthly = $stripe->retrievePrice($model->stripe_priceID->monthly);
            if ($monthly && $monthly->unit_amount != $model->monthly_fee * 100) {
                $monthly->update($monthly->id, [
                    "active" => false
                ]);
                $monthly = $this->handleMonthlyPrice($model, $stripe);
            }

            $yearly = $stripe->retrievePrice($model->stripe_priceID->yearly);
            if ($yearly && $yearly->unit_amount != $model->yearly_fee * 100) {
                $yearly->update($yearly->id, [
                    "active" => false
                ]);
                $yearly = $this->handleYearlyPrice($model, $stripe);
            }

            $model->update([
                'stripe_priceID' => [
                    'monthly' => $monthly->id,
                    'yearly' => $yearly->id
                ]
            ]);
            return 0;
        } else {
            $monthlyPrice = $this->handleMonthlyPrice($model, $stripe);
            $yearlyPrice = $this->handleYearlyPrice($model, $stripe);
            $model->update([
                'stripe_priceID' => [
                    'monthly' => $monthlyPrice->id,
                    'yearly' => $yearlyPrice->id
                ]
            ]);
            return 0;
        }
    }
    protected function handleMonthlyPrice(
        Model $model,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->createPrice([
            'currency' => SubscriptionConstant::CURRENCY_USD,
            'unit_amount' => $model->monthly_fee * 100,
            'product' => $model->stripe_productID,
            'active' => $model->is_active
        ]);
    }

    protected function handleYearlyPrice(
        Model $model,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->createPrice([
            'currency' => SubscriptionConstant::CURRENCY_USD,
            'unit_amount' => $model->yearly_fee * 100,
            'product' => $model->stripe_productID,
            'active' => $model->is_active
        ]);
    }
}
