<?php

namespace App\Traits\Subscription;

use App\Constants\SubscriptionConstant;
use App\Services\ThirdPartyApi\Stripe\Stripe;
use Illuminate\Database\Eloquent\Model;

trait SubscriptionPlanTrait
{

    protected static function boot(): void
    {
        $stripe = new Stripe();

        static::created(function (Model $model) use ($stripe) {
            if ($model->is_free) {
                return 0;
            }
            $product = $stripe->createProduct([
                'name' => $model->title,
                'description' => $model->short_description,
                'active' => $model->is_active,
                'metadata' => [
                    'default_seat_count' => $model->default_seat || SubscriptionConstant::DEFAULT_SEAT_COUNT,
                ],
            ]);

            $monthPrice = $this->createPrice($product->id, $model->monthly_fee, 'month', 'base', $stripe);
            $annualPrice = $this->createPrice($product->id, $model->yearly_fee, 'year', 'base', $stripe);

            $monthSeatPrice = $this->createPrice($product->id, floatval($model->seat_amount ?? 0.00), 'month', 'per_user', $stripe);
            $annualSeatPrice = $this->createPrice($product->id, floatval($model->seat_amount ?? 0.00), 'year', 'per_user', $stripe);


            $model->update([
                'provider_product_id' => $product->id,
                'provider_price_ids' => [
                    'monthly' => $monthPrice->id,
                    'annually' => $annualPrice->id
                ],
                'provider_seat_amount_ids' => [
                    'monthly' => $monthSeatPrice->id,
                    'annually' => $annualSeatPrice->id
                ]
            ]);
        });

        static::updated(function (Model $model) use ($stripe) {
            if ($model->is_free) {
                return 0;
            }
            $stripe->updateProduct(
                $model->provider_product_id,
                [
                    'name' => $model->title,
                    'description' => $model->short_description,
                    'active' => $model->is_active,
                    'metadata' => [
                        'default_seat_count' => $model->default_seat || SubscriptionConstant::DEFAULT_SEAT_COUNT,
                    ],
                ]
            );


            if ($model->wasChanged('monthly_fee')) {
                $this->updatePrice($model->provider_price_ids->monthly, $model->monthly_fee, $stripe);
            }

            if ($model->wasChanged('yearly_fee')) {
                $this->updatePrice($model->provider_price_ids->annually, $model->yearly_fee, $stripe);
            }

            if ($model->wasChanged('seat_amount')) {
                $this->updatePrice($model->provider_seat_amount_ids->monthly, floatval($model->seat_amount ?? 0.00), $stripe);
                $this->updatePrice($model->provider_seat_amount_ids->annually, floatval($model->seat_amount ?? 0.00), $stripe);
            }
        });

        static::deleted(function (Model $model) use ($stripe) {
            if ($model->is_free) {
                return 0;
            }
            $stripe->deleteProduct($model->provider_product_id);
        });
    }

    protected function createPrice(
        string $product_id,
        float $amount,
        string $interval,
        string $type,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->createPrice([
            'currency' => SubscriptionConstant::CURRENCY_USD,
            'unit_amount' => $amount * 100,
            'product' => $product_id,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => 1,
            ],
            'metadata' => ['type' => $type]
        ]);
    }

    protected function updatePrice(
        string $price_id,
        float $amount,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->updatePrice($price_id, [
            'currency' => SubscriptionConstant::CURRENCY_USD,
            'unit_amount' => $amount * 100,
        ]);
    }

    protected function deactivatePrice(
        string $price_id,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->updatePrice($price_id, ["active" => false]);
    }
}
