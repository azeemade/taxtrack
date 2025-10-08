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
                'active' => boolval($model->is_active),
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

        // Skip if it's a free plan
        if ($model->is_free) {
            return;
        }

        $stripe = $this->stripe;

        if (!$model->is_free) {
            $stripe->updateProduct(
                $model->provider_product_id,
                [
                    'name' => $model->title,
                    'description' => $model->short_description,
                    'active' => boolval($model->is_active),
                    'metadata' => [
                        'default_seat_count' => $model->default_seat ?? SubscriptionConstant::DEFAULT_SEAT_COUNT,
                    ],
                ]
            );

            $oldModel = $model->getOriginal();
            // if ($model->isDirty('monthly_fee')) {
            if ($oldModel['monthly_fee'] != $model->monthly_fee) {
                $priceIds = is_array($model->provider_price_ids) ? $model->provider_price_ids : json_decode($model->provider_price_ids, true);
                if (empty($priceIds)) {
                    $monthlyPrice = $this->createPrice(
                        $model->provider_product_id,
                        $model->monthly_fee,
                        'month',
                        'base',
                        $stripe
                    );
                    $monthlyPrice = $monthlyPrice->toArray();
                } else {
                    $monthlyPrice = $this->updatePrice(
                        $model->provider_product_id,
                        $oldModel['provider_price_ids']['monthly'],
                        $model->monthly_fee,
                        'month',
                        'base',
                        $stripe
                    );
                    $monthlyPrice = $monthlyPrice->toArray();
                }
                $model->updateQuietly(['provider_price_ids' => [
                    ...$priceIds,
                    'monthly' => $monthlyPrice['id'],
                ]]);
            }

            if ($oldModel['yearly_fee'] != $model->yearly_fee) {
                $priceIds = is_array($model->provider_price_ids) ? $model->provider_price_ids : json_decode($model->provider_price_ids, true);
                if (empty($priceIds) || empty($priceIds['annually'])) {
                    $annualPrice = $this->createPrice(
                        $model->provider_product_id,
                        $model->yearly_fee,
                        'year',
                        'base',
                        $stripe
                    );
                    $annualPrice = $annualPrice->toArray();
                } else {
                    $annualPrice = $this->updatePrice(
                        $model->provider_product_id,
                        $oldModel['provider_price_ids']['annually'],
                        $model->yearly_fee,
                        'year',
                        'base',
                        $stripe
                    );
                    $annualPrice = $annualPrice->toArray();
                }

                $model->updateQuietly(['provider_price_ids' => [
                    ...$priceIds,
                    'annually' => $annualPrice['id'],
                ]]);
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
            $stripe->updateProduct($model->provider_product_id, ["active" => false]);
            // $stripe->deleteProduct($model->provider_product_id);
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
        if ($type == 'base') {
            $plan = $stripe->createPlan([
                'amount' => $amount * 100,
                'currency' => SubscriptionConstant::CURRENCY_GBP,
                'interval' => $interval,
                'product' => $product_id,
            ]);
        }

        return $stripe->createPrice([
            'currency' => SubscriptionConstant::CURRENCY_GBP,
            'unit_amount' => $amount * 100,
            'product' => $product_id,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => 1,
            ],
            'metadata' => ['type' => $type, 'plan_id' => $plan->id ?? null]
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
        $price = $stripe->retrievePrice($price_id);
        if (
            isset($price['metadata']['plan_id']) &&
            $price['metadata']['type'] == 'base' &&
            $price['metadata']['plan_id'] != null
        ) {
            $stripe->updatePlan($price['metadata']['plan_id'], ["active" => false]);
        }
        return $stripe->updatePrice($price_id, ["active" => false]);
    }
}
