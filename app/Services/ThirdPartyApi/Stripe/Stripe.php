<?php

namespace App\Services\ThirdPartyApi\Stripe;

use Illuminate\Support\Facades\Http;

class Stripe
{
    protected $stripe;

    public function __construct()
    {
        $key = config('stripe.secret');
        $this->stripe = new \Stripe\StripeClient($key);
    }


    /**
     * 
     * Product section
     */

    public function createProduct($data)
    {
        return $this->stripe->products->create($data);
    }

    public function updateProduct($data)
    {
        return $this->stripe->products->update($data);
    }

    public function deleteProduct(string $id)
    {
        return $this->stripe->products->delete($id);
    }


    /**
     * 
     * Price section
     */

    public function createPrice($data)
    {
        return $this->stripe->prices->create($data);
    }

    public function retrievePrice(string $id)
    {
        return $this->stripe->prices->retrieve($id);
    }


    /**
     * 
     * Customer section
     */

    public function createCustomer($data)
    {
        return $this->stripe->customers->create($data);
    }


    /**
     * 
     * Subscription section
     */

    public function createSubscription($data)
    {
        return $this->stripe->subscriptions->create($data);
    }
}
