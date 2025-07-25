<?php

namespace App\Observers;

use App\Constants\SubscriptionConstant;
use App\Models\SubscriptionPlan;
use App\Services\ThirdPartyApi\Stripe\Stripe;

class SubscriptionPlanObserver
{
    protected $stripe;
    public function __construct()
    {
        $this->stripe = new Stripe();
    }
    /**
     * Handle the SubscriptionPlan "created" event.
     */
    public function created(SubscriptionPlan $model): void
    {
        $stripe = $this->stripe;

        if (!$model->is_free) {
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
        }
    }

    /**
     * Handle the SubscriptionPlan "updated" event.
     */
    public function updated(SubscriptionPlan $model): void
    {
        // Prevent running on creation
        if ($model->wasRecentlyCreated) {
            return;
        }

        $stripe = $this->stripe;

        if (!$model->is_free) {
            $stripe->updateProduct(
                $model->provider_product_id,
                [
                    'name' => $model->title,
                    'description' => $model->short_description,
                    'active' => $model->is_active,
                    'metadata' => [
                        'default_seat_count' => $model->default_seat ?? SubscriptionConstant::DEFAULT_SEAT_COUNT,
                    ],
                ]
            );

            if ($model->wasChanged('monthly_fee')) {
                $this->updatePrice(
                    $model->provider_product_id,
                    $model->provider_price_ids['monthly'],
                    $model->monthly_fee,
                    'month',
                    'base',
                    $stripe
                );
            }

            if ($model->wasChanged('yearly_fee')) {
                $this->updatePrice(
                    $model->provider_product_id,
                    $model->provider_price_ids['annually'],
                    $model->yearly_fee,
                    'year',
                    'base',
                    $stripe
                );
            }

            if ($model->wasChanged('seat_amount')) {
                $this->updatePrice(
                    $model->provider_product_id,
                    $model->provider_seat_amount_ids['monthly'],
                    floatval($model->seat_amount ?? 0.00),
                    'month',
                    'per_user',
                    $stripe
                );
                $this->updatePrice(
                    $model->provider_product_id,
                    $model->provider_seat_amount_ids['annually'],
                    floatval($model->seat_amount ?? 0.00),
                    'year',
                    'per_user',
                    $stripe
                );
            }
        }
    }

    /**
     * Handle the SubscriptionPlan "deleted" event.
     */
    public function deleted(SubscriptionPlan $model): void
    {
        $stripe = $this->stripe;
        if (!$model->is_free) {
            $stripe->deleteProduct($model->provider_product_id);
        }
    }

    /**
     * Handle the SubscriptionPlan "restored" event.
     */
    public function restored(SubscriptionPlan $subscriptionPlan): void
    {
        //
    }

    /**
     * Handle the SubscriptionPlan "force deleted" event.
     */
    public function forceDeleted(SubscriptionPlan $subscriptionPlan): void
    {
        //
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
        string $product_id,
        string $price_id,
        float $amount,
        string $interval,
        string $type,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        $this->deactivatePrice($price_id, $stripe);
        return $this->createPrice(
            $product_id,
            $amount,
            $interval,
            $type,
            $stripe
        );
    }

    protected function deactivatePrice(
        string $price_id,
        \App\Services\ThirdPartyApi\Stripe\Stripe $stripe,
    ) {
        return $stripe->updatePrice($price_id, ["active" => false]);
    }
}
