<?php

namespace App\Services\ManageSubscriptionServices;

use App\Constants\SubscriptionConstant;
use App\Enums\GeneralEnums;
use App\Enums\SubscriptionPlanDurationEnums;
use App\Enums\SubscriptionRefundEnums;
use App\Exceptions\BadRequestException;
use App\Models\Company;
use App\Models\Subscriber;
use App\Models\SubscriptionCancellation;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRefund;
use App\Models\User;
use App\Services\ThirdPartyApi\Stripe\Stripe;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CompanySubscriptionService
{
    protected $stripe;
    public function __construct()
    {
        $this->stripe = new Stripe();
    }

    public function subscriptionHistory($request, ?int $subscriber_id = null)
    {
        $records = SubscriptionHistory::query()
            ->subscriber()
            ->select('id', 'subscribed_at', 'subscription_plan_id', 'billed_per', 'status', 'amount_paid', 'plan_amount', 'subscribed_at', 'end_date', 'receipt_no')
            ->with([
                'plan:id,title',
            ])
            ->when($subscriber_id, function ($query) use ($subscriber_id) {
                $query->where('subscriber_id', $subscriber_id);
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('subscribed_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('subscribed_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->whereRelation('plan', 'title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('receipt_no', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('amount_paid', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('subscribed_at', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function viewHistory($id)
    {
        $record = SubscriptionHistory::query()
            ->select(
                'id',
                'subscribed_at',
                'subscription_plan_id',
                'billed_per',
                'paid_via',
                'amount_paid',
                'plan_amount',
                'end_date',
                'receipt_no',
                'subscriber_id',
                'customer_refer_no',
                'provider_subscription_id'
            )
            ->with([
                'plan:id,title',
                'subscriber:id,company_id' => ['company:id,name,address,logo'],
            ])
            ->find($id);

        return $record;
    }

    public function viewRefund($id)
    {
        $record = SubscriptionRefund::find($id);

        return $record;
    }

    public function viewCancellation($id)
    {
        $record = SubscriptionCancellation::find($id);

        return $record;
    }

    public function refundRequests($request, ?int $subscriber_id = null)
    {
        $records = SubscriptionRefund::query()
            ->select('id', 'request_date', 'refund_type', 'amount_refunded', 'reason', 'status', 'subscription_plan_id')
            ->with([
                'subscriptionPlan:id,title',
            ])
            ->when($subscriber_id, function ($query) use ($subscriber_id) {
                $query->where('subscriber_id', $subscriber_id);
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('request_date', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('request_date', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->whereRelation('subscriptionPlan', 'title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('reason', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('amount_refunded', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('request_date', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    /** ✅
     * 
     * Refunding after a subscription has been completed, a fee is charged from stripe
     * 
     * @param $request
     * @param string $request['reason']
     * @param string|null $request['additional_information']
     */
    public function createRefundRequest($request)
    {
        $currentUser = Auth::udser();
        $subscriber = $this->findSubscriber(
            $currentUser->current_company_id
        );

        if (!$subscriber) {
            throw new BadRequestException('Subscriber not found', Response::HTTP_BAD_REQUEST);
        }

        if ($subscriber->currentSubscriptionHistory->plan->is_free) {
            throw new BadRequestException('You cannot refund a free subscription', Response::HTTP_BAD_REQUEST);
        }

        $invoices = $this->stripe->listInvoices([
            'customer' => $subscriber->provider_customer_id,
            'subscription' => $subscriber->currentSubscriptionHistory->provider_subscription_id,
            'limit' => 1,
        ]);

        if (!count($invoices['data'])) {
            throw new BadRequestException('No subscription found to refund', Response::HTTP_BAD_REQUEST);
        }

        /**
         * 
         * Use webhook to track refund incase it gets updated on stripe
         */
        $refund = SubscriptionRefund::create([
            'request_date' => Carbon::now(),
            'amount_refunded' => $this->calculateSubscriptionProration($subscriber->currentSubscriptionHistory) * SubscriptionConstant::PRORATED_REFUND_PERCENTAGE,
            'refund_type' => SubscriptionRefundEnums::PARTIAL_REFUND->value,
            'status' => GeneralEnums::PENDING->value,
            'reason' => $request['reason'],
            'additional_information' => $request['additional_information'],
            'subscription_history_id' => $subscriber->currentPlanHistory->id,
            'company_id' => $currentUser->current_company_id,
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $subscriber->current_subscription_plan_id,
        ]);

        return $refund;
    }

    public function cancellationRequests($request, ?int $subscriber_id = null)
    {
        $records = SubscriptionCancellation::query()
            ->select('id', 'request_date', 'effective_from', 'reason', 'status', 'subscription_plan_id')
            ->with([
                'subscriptionPlan:id,title',
            ])
            ->when($subscriber_id, function ($query) use ($subscriber_id) {
                $query->where('subscriber_id', $subscriber_id);
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('request_date', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('request_date', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->whereRelation('subscriptionPlan', 'title', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('reason', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('amount_refunded', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('request_date', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    /** ✅
     * 
     * @param $request
     * @param string $request['reason']
     * @param string|null $request['additional_information']
     * @param string|null $request['effective_from']
     */
    public function cancelPlan($request)
    {
        $currentUser = Auth::user();
        $subscriber = $this->findSubscriber(
            $currentUser->current_company_id
        );

        if (!$subscriber) {
            throw new BadRequestException('Subscriber not found', Response::HTTP_BAD_REQUEST);
        }

        $cancellation = SubscriptionCancellation::create([
            'request_date' => Carbon::now(),
            'effective_from' => $request['effective_from'] == 'instant' ? Carbon::now() : $subscriber->currentPlanHistory->end_date,
            'status' => GeneralEnums::PENDING->value,
            'reason' => $request['reason'],
            'additional_information' => $request['additional_information'] ?? null,
            'subscription_history_id' => $subscriber->currentPlanHistory->id,
            'company_id' => $currentUser->current_company_id,
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $subscriber->current_subscription_plan_id,
        ]);

        if ($request['effective_from'] == 'instant') {
            $cancellation->subscriptionHistory()->update([
                'status' => GeneralEnums::CANCELLED->value,
            ]);
            $this->stripe->cancelSubscription($cancellation->subscriptionHistory->provider_subscription_id);
        } else {
            $this->stripe->updateSubscription($cancellation->subscriptionHistory->provider_subscription_id, [
                'cancel_at_period_end' => true,
                'metadata' => [
                    'reason' => $request['reason'],
                    'additional_information' => $request['additional_information'] ?? null,
                ]
            ]);
        }

        return $cancellation;
    }

    public function currentPlan()
    {
        $currentUser = Auth::user();
        $record = Subscriber::select('id', 'current_subscription_plan_id', 'company_id')
            ->with([
                'subscriptionPlan:id,title,short_description',
                'currentSubscriptionHistory:id,billed_per,amount_paid,end_date,status,subscribed_at,subscriber_id',
            ])
            ->where('company_id', $currentUser->current_company_id)
            ->first();
        return $record;
    }

    /** ✅
     * @param $request
     *  @param int|null $request['user_id']
     *  @param int|null $request['company_id']
     *  @param int|null $request['subscription_plan_id']
     *  @param bool|null $request['is_free']
     *  @param string $request['duration']
     *  @param int|null $request['additional_users_count']
     *  @param string|null $request['provider_payment_method_id']
     *  @param bool|null $request['save_card']
     *  @param bool|null $request['use_credit_balance']
     */
    public function subscribeToPlan($request)
    {
        /**
         * Web hook is required for auto renewal of subscription on stripe
         */
        $currentUser = Auth::check() ? Auth::user() : User::find($request['user_id']);
        if (!$currentUser) {
            throw new BadRequestException('User not found');
        }

        $company = isset($request['company_id']) ? Company::find($request['company_id']) : $currentUser->company;

        if (!$company) {
            throw new BadRequestException('Company not found');
        }

        if (!isset($request['subscription_plan_id']) && !isset($request['is_free'])) {
            throw new BadRequestException('Subscription plan is required');
        }

        $plan = $this->getPlan([
            'subscription_plan_id' => $request['subscription_plan_id'] ?? null,
            'is_free' => $request['is_free'] ?? false,
        ]);

        if (!$plan || !$plan->is_active || $plan->status === GeneralEnums::INACTIVE->value) {
            throw new BadRequestException('Plan not found or is inactive');
        }

        $subscriber = $this->handleSubscriber([
            'company_id' => $company->id,
            'user_id' => $currentUser->id,
            'name' => $company->name,
            'email' => $currentUser->email,
        ]);

        $durationDependencies = $this->getPlanDurationDependencies($request['duration'], $plan, $request['is_free'] ?? false);

        //load credit note from stripe here
        $amountPaid =  $this->calculateSubscriptionCosts(
            $durationDependencies['plan_amount'],
            $durationDependencies['seat_amount'],
            $request['use_credit_balance'] ?? false,
            $request['additional_users_count'] ?? 0
        );

        $subscription = SubscriptionHistory::create([
            'receipt_no' => "RCP-" . date('YmdHis'),
            'customer_refer_no' => "RCP-" . date('YmdHis'),
            'billed_per' => $request['duration'] === SubscriptionPlanDurationEnums::MONTHLY->value ? 'month' : 'year',
            'additional_charge' => $request['additional_charge'] ?? 0.00,
            'tax' => $request['tax'] ?? 0.00,
            'team_size' => $request['additional_users_count'] ?? 0 + SubscriptionConstant::DEFAULT_SEAT_COUNT,
            'sub_total' => $amountPaid['sub_total'] ?? 0.00,
            'payment_method_id' => $paymentMethod->id ?? null,
            'payment_type' => 'card',
            'amount_paid' => $amountPaid['total'] ?? 0.00,
            'plan_amount' => $durationDependencies['plan_amount'],
            'end_date' => isset($request['is_free']) && $request['is_free'] ? Carbon::now()->addDays($plan->duration) : $durationDependencies['end_date'],
            'status' => GeneralEnums::ACTIVE->value,
            'subscribed_at' => Carbon::now(),
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $plan->id,
        ]);

        $subscriber->update([
            'current_subscription_plan_id' => $request['subscription_plan_id'],
            'credit_balance' => $subscriber->credit_balance - $amountPaid['credit_note_balance'],
        ]);

        if (isset($request['is_free']) && $request['is_free']) {
            return;
        }

        $this->stripe->attachPaymentMethod($request['provider_payment_method_id'], ['customer' => $subscriber->provider_customer_id]);

        if (isset($request['save_card']) && $request['save_card']) {
            $this->stripe->updateCustomer($subscriber->provider_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $request['provider_payment_method_id']
                ]
            ]);
        }

        if ($request['action'] === 'upgrade') {
            $providerSubscription = $this->changePlan(
                $subscriber->provider_customer_id,
                $request['additional_users_count'] ?? 0,
                $durationDependencies['provider_price_id'],
                $durationDependencies['provider_seat_price_id']
            );
        } else {
            $subscriptionItems = [
                [
                    'price' => $durationDependencies['provider_price_id'],
                    'metadata' => ['type' => 'base']
                ],
            ];

            if (isset($request['additional_users_count']) && $request['additional_users_count'] > 0 && $durationDependencies['provider_seat_price_id']) {
                $subscriptionItems[] = [
                    'price' => $durationDependencies['provider_seat_price_id'],
                    'quantity' => $request['additional_users_count'],
                    'metadata' => ['type' => 'per_user']
                ];
            }
            $providerSubscription = $this->createNewSubscription($subscriber->provider_customer_id, $subscriptionItems);
        }

        $subscription->update([
            'provider_subscription_id' => $providerSubscription['id'],
        ]);


        if ($amountPaid['credit_note_balance'] > 0) {
            $this->stripe->payInvoice($providerSubscription->latest_invoice->id, [
                'paid_out_of_band' => true,
            ]);
        }
        if ($amountPaid['total'] > 0) {
            $this->stripe->confirmPaymentIntent($providerSubscription->latest_invoice->payment_intent->id, [
                'payment_method' => $request['provider_payment_method_id'],
                'payment_method_options' => ['card' => ['request_three_d_secure' => 'any']],
            ]);
        }
    }

    protected function createNewSubscription($customer_id, $subscriptionItems)
    {
        return $this->stripe->createSubscription([
            'customer' => $customer_id,
            'items' => $subscriptionItems,
            'collection_method' => 'charge_automatically',
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'payment_settings' => [
                'payment_method_types' => ['card'],
                'save_default_payment_method' => 'on_subscription',
            ],
        ]);
    }

    public function plans()
    {
        return SubscriptionPlan::with(['subscriptionPlanFeature:id,title,subscription_plan_id'])
            ->where('status', GeneralEnums::ACTIVE->value)
            ->where('is_active', true)
            ->where('is_free', false)
            ->get();
    }

    public function subscriptionCards($request)
    {
        $records = SubscriptionPaymentMethod::query()
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('name', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('issuer', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function calculateSubscriptionCosts($plan_amount, $seat_amount, $use_credit_balance = false, $additional_users_count)
    {
        $additional_amount = $seat_amount * $additional_users_count;
        $subtotal_before_credit_balance = $plan_amount + $additional_amount;
        $credit_balance = 0.00;
        $usable_credit_balance = $use_credit_balance ? $credit_balance : 0.00;
        $subtotal = $usable_credit_balance > $subtotal_before_credit_balance ? 0.00 : $subtotal_before_credit_balance - $usable_credit_balance;
        $tax = 0; //$subtotal * SubscriptionConstant::TAX_RATE;
        $total = $subtotal + $tax;

        return [
            'credit_note_balance' => $usable_credit_balance,
            'additional_amount' => $additional_amount,
            'tax' => $tax,
            'sub_total' => $subtotal,
            'total' => $total,
        ];
    }

    public function getPlan($data)
    {
        $plan = SubscriptionPlan::query();
        $planCheck = isset($data['is_free']) && $data['is_free'] ?
            $plan->where('is_free', true) :
            $plan->where('id', $data['subscription_plan_id']);

        return $planCheck->first();
    }

    public function delete($id)
    {
        $record = SubscriptionPaymentMethod::find($id);
        if (!$record) {
            throw new BadRequestException("Payment method not found!");
        }
        $record->delete();
    }

    protected function handleSubscriber($data)
    {
        $subscriber = Subscriber::firstOrCreate(
            [
                'company_id' => $data['company_id'],
            ],
            [
                'name' => $data['name'],
                'company_id' => $data['company_id'],
                'credit_balance' => $request['credit_balance'] ?? 0.00,
                'user_id' => $data['user_id'],
                'current_subscription_plan_id' => $data['subscription_plan_id'] ?? null,
            ]
        );

        if (!$subscriber->provider_customer_id) {
            $customer = $this->stripe->createCustomer([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'metadata' => [
                    'company_id' => $data['company_id'],
                ],
            ]);
            $subscriber->update(['provider_customer_id' => $customer->id]);
        }
        return $subscriber;
    }

    protected function findSubscriber($companyId)
    {
        return Subscriber::where(
            'company_id',
            $companyId,

        )->first();
    }

    protected function getPlanDurationDependencies($duration, $plan, $is_free)
    {
        $dependencies = [];
        if ($is_free || $duration === SubscriptionPlanDurationEnums::MONTHLY->value) {
            $dependencies['plan_amount'] = $plan->monthly_fee;
            $dependencies['end_date'] = Carbon::now()->addMonth();
            $dependencies['seat_amount'] = $plan->seat_amount['monthly'] ?? 0;
            $dependencies['provider_price_id'] = $plan->provider_price_ids['monthly'] ?? null;
            $dependencies['provider_seat_price_id'] = $plan->provider_seat_amount_ids['monthly'] ?? null;
        } else {
            $dependencies['plan_amount'] = $plan->yearly_fee;
            $dependencies['end_date'] = Carbon::now()->addYear();
            $dependencies['seat_amount'] = $plan->seat_amount['annually'] ?? 0;
            $dependencies['provider_price_id'] = $plan->provider_price_ids['annually'];
            $dependencies['provider_seat_amount_id'] = $plan->provider_seat_amount_ids['annually'];
        }
        return $dependencies;
    }

    protected function changePlan($provider_customer_id, $additional_users_count, $provider_price_id, $provider_seat_price_id)
    {
        $subscriptions = $this->stripe->allSubscriptions(['customer' => $provider_customer_id]);

        $latestSubscription = $subscriptions['data'][0];
        $baseSubscriptionItem = $latestSubscription['items']['data'][0]['metadata']['type'] === 'base' ? $latestSubscription['items']['data'][0] : $latestSubscription['items']['data'][1];

        $subscriptionItem = [
            'id' => $baseSubscriptionItem['id'],
            'price' => $provider_price_id,
        ];

        $seatSubscriptionItem = !isset($latestSubscription['items']['data'][1]) ? null : ($latestSubscription['items']['data'][1]['metadata']['type'] === 'per_user' ? $latestSubscription['items']['data'][1] : $latestSubscription['items']['data'][0]);
        $seatPriceId = $seatSubscriptionItem['price']['id'] ?? null;
        $additionalUsers = isset($additional_users_count) && $additional_users_count > 0;

        if ($seatPriceId) {
            $subscriptionItems[] = [
                'id' => $seatSubscriptionItem['id'],
                'deleted' => true,
            ];
        }

        if ($additionalUsers) {
            $subscriptionItems[] = [
                'price' => $provider_seat_price_id,
                'quantity' => $additionalUsers,
            ];
        }

        return $this->stripe->updateSubscription(
            $latestSubscription['id'],
            [
                'items' => $subscriptionItem,
                'expand' => ['latest_invoice.payment_intent'],
            ]
        );
    }

    protected function calculateSubscriptionProration($subscriptionHistory)
    {
        $currentDate = Carbon::now();
        $usedDuration = $currentDate->diffInDays($subscriptionHistory->subscribed_at);
        $totalDuration = $subscriptionHistory->end_date->diffInDays($subscriptionHistory->subscribed_at);
        $remainingDuration = $totalDuration - $usedDuration;
        $remainingAmount = $subscriptionHistory->amount_paid * ($remainingDuration / $totalDuration);
        return $subscriptionHistory->amount_paid - $remainingAmount;
    }
}
