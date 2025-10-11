<?php

namespace App\Services\ThirdPartyApi\Stripe;

use Illuminate\Support\Facades\Http;

class Stripe
{
    protected $stripe;

    public function __construct()
    {
        $key = config('stripe.stripe_secret');
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

    public function updateProduct($id, $data)
    {
        return $this->stripe->products->update($id, $data);
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

    public function updatePrice($id, $data)
    {
        return $this->stripe->prices->update($id, $data);
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

    public function updateCustomer($id, $data)
    {
        return $this->stripe->customers->update($id, $data);
    }


    /**
     * 
     * Subscription section
     */
    public function createSubscription($data)
    {
        return $this->stripe->subscriptions->create($data);
    }

    public function cancelSubscription($id)
    {
        return $this->stripe->subscriptions->cancel($id);
    }

    public function updateSubscription($id, $data)
    {
        return $this->stripe->subscriptions->update($id, $data);
    }

    public function allSubscriptions($data)
    {
        return $this->stripe->subscriptions->all($data);
    }

    /**
     * 
     * Payment method section
     */

    public function attachPaymentMethod($id, $data)
    {
        return $this->stripe->paymentMethods->attach($id, $data);
    }

    public function retrievePaymentMethod($id)
    {
        return $this->stripe->paymentMethods->retrieve($id);
    }

    /**
     * 
     * Invoice section
     */

    public function payInvoice($id, $data)
    {
        return $this->stripe->invoices->pay($id, $data);
    }

    public function listInvoices($data)
    {
        return $this->stripe->invoices->all($data);
    }

    /**
     * 
     * Payment intent section
     */

    public function confirmPaymentIntent($id, $data)
    {
        return $this->stripe->paymentIntents->confirm($id, $data);
    }

    public function retrievePaymentIntent($id)
    {
        return $this->stripe->paymentIntents->retrieve($id);
    }

    /**
     * 
     * Refund section
     */

    public function createRefund($data)
    {
        return $this->stripe->refunds->create($data);
    }

    public function createPlan($data)
    {
        return $this->stripe->plans->create($data);
    }

    public function updatePlan($id, $data)
    {
        return $this->stripe->plans->update($id, $data);
    }
}
