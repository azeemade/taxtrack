<?php

namespace App\Services\ManageSubscriptionServices;

use App\Enums\GeneralEnums;
use App\Enums\SubscriptionPlanDurationEnums;
use App\Enums\SubscriptionRefundEnums;
use App\Models\Company;
use App\Models\Subscriber;
use App\Models\SubscriptionCancellation;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRefund;
use Carbon\Carbon;

class CompanySubscriptionService
{
    public function subscriptionHistory($request)
    {
        $records = SubscriptionHistory::query()
            ->select('id', 'subscribed_at', 'subscription_plan_id', 'billed_per', 'status', 'amount_paid', 'plan_amount', 'subscribed_at', 'end_date', 'receipt_no')
            ->with([
                'plan:id,title',
            ])
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
            ->select('id', 'subscribed_at', 'subscription_plan_id', 'billed_per', 'paid_via', 'amount_paid', 'plan_amount', 'end_date', 'receipt_no')
            ->with([
                'plan:id,title',
                'subscriber:id,company_id' => ['company:id,name,address,logo'],
            ])
            ->find($id);

        return $record;
    }

    public function refundRequests($request)
    {
        $records = SubscriptionRefund::query()
            ->select('id', 'request_date', 'refund_type', 'amount_refunded', 'reason', 'status', 'subscription_plan_id')
            ->with([
                'subscriptionPlan:id,title',
            ])
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
                return $query->whereRelation('plan', 'title', 'LIKE', '%' . $request->q . '%')
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

    public function createRefundRequest($request)
    {
        $currentUser = auth()->user();
        $subscriber = Subscriber::where('company_id', $currentUser->current_company_id)
            ->where('user_id', $currentUser->id)
            ->first();

        return SubscriptionRefund::create([
            'request_date' => Carbon::now(),
            'refund_type' => SubscriptionRefundEnums::PARTIAL_REFUND->value,
            'status' => GeneralEnums::PENDING->value,
            'reason' => $request['reason'],
            'additional_information' => $request['additional_information'],
            'subscription_history_id' => $subscriber->currentPlanHistory->id,
            'company_id' => $currentUser->current_company_id,
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $subscriber->current_subscription_plan_id,
        ]);
    }

    public function cancellationRequests($request)
    {
        $records = SubscriptionCancellation::query()
            ->select('id', 'request_date', 'effective_from', 'reason', 'status', 'subscription_plan_id')
            ->with([
                'subscriptionPlan:id,title',
            ])
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
                return $query->whereRelation('plan', 'title', 'LIKE', '%' . $request->q . '%')
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

    public function cancelPlan($request)
    {
        $currentUser = auth()->user();
        $subscriber = Subscriber::where('company_id', $currentUser->current_company_id)
            ->where('user_id', $currentUser->id)
            ->first();

        return SubscriptionCancellation::create([
            'request_date' => Carbon::now(),
            'effective_from' => $request['effective_from'] == 'instant' ? Carbon::now() : $subscriber->currentPlanHistory->end_date,
            'status' => GeneralEnums::PENDING->value,
            'reason' => $request['reason'],
            'additional_information' => $request['additional_information'],
            'subscription_history_id' => $subscriber->currentPlanHistory->id,
            'company_id' => $currentUser->current_company_id,
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $subscriber->current_subscription_plan_id,
        ]);
    }

    public function currentPlan()
    {
        $currentUser = auth()->user();
        $record = Subscriber::select('id', 'current_subscription_plan_id', 'company_id')
            ->with([
                'subscriptionPlan:id,title,short_description',
                'currentPlanHistory:id,billed_per,amount_paid,end_date,status,subscribed_at,subscriber_id',
            ])
            ->where('company_id', $currentUser->current_company_id)
            ->first();
        return $record;
    }

    public function subscribeToPlan($request)
    {
        $subscriber = $this->handleSubscriber($request);

        //Todo: make payment here
        if ($request['save_card']) {
            $paymentMethod = $this->createSubscriptionPaymentMethod($request);
        }

        SubscriptionHistory::create([
            'billed_per' => $request['duration'] === SubscriptionPlanDurationEnums::MONTHLY->value ? 'month' : 'year',
            'additional_charge' => $request['additional_charge'],
            'tax' => $request['tax'],
            'team_size' => $request['additional_users_count'] + 4,
            'sub_total' => $request['sub_total'],
            'payment_method_id' => $paymentMethod->id,
            'amount_paid' => $request['total'],
            'plan_amount' => $request['total'],
            'end_date' => $request['duration'] === SubscriptionPlanDurationEnums::MONTHLY->value ? Carbon::now()->addMonth() : Carbon::now()->addYear(),
            'status' => GeneralEnums::ACTIVE->value,
            'subscribed_at' => Carbon::now(),
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $request['subscription_plan_id'],
        ]);


        $subscriber->update([
            'current_subscription_plan_id' => $request['subscription_plan_id'],
            'credit_balance' => $request['credit_balance'] ?? $subscriber->credit_balance,
        ]);

        // if (isset($request['use_credit_balance']) && $request['use_credit_balance']) {
        //     $subscriber->update([
        //         'credit_balance' => $request['credit_balance']
        //     ]);
        // }
    }

    public function plans()
    {
        return SubscriptionPlan::with(['subscriptionPlanFeature:id,title,subscription_plan_id'])
            ->where('status', GeneralEnums::ACTIVE->value)
            ->where('is_active', true)
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

    public function computeTotal($request)
    {
        $currentUser = auth()->user();

        $plan = SubscriptionPlan::find($request['subscription_plan_id']);
        $subscriber = Subscriber::where('company_id', $currentUser->current_company_id)
            ->where('user_id', $currentUser->id)
            ->first();

        $amount = $request['duration'] === SubscriptionPlanDurationEnums::MONTHLY->value ? $plan->monthly_fee : $plan->yearly_fee;
        $tax = 0;
        $additional_user_charge = 2; //per month in usd
        $new_credit_balance = $subscriber->credit_balance;
        $additional_charge = $request['additional_users_count'] * $additional_user_charge;
        $sub_total = $additional_charge + $amount;
        $total = $sub_total + $tax + $additional_charge;

        if (isset($request['use_credit_balance']) && $request['use_credit_balance']) {
            $new_credit_balance = $subscriber->credit_balance > $total ? $subscriber->credit_balance - $total : 0.00;

            $total = $subscriber->credit_balance > $total ? 0.00 : $total - $subscriber->credit_balance;
        }

        return [
            "tax" => $tax,
            "credit_balance" => $new_credit_balance ?? 0.00,
            "additional_charge" => $additional_charge,
            "sub_total" => $sub_total,
            "total" => (float) ($total)
        ];
    }

    protected function handleSubscriber($request)
    {
        $currentUser = auth()->user();

        return Subscriber::firstOrCreate(
            [
                'company_id' => $currentUser->current_company_id,
                'user_id' => $currentUser->id,
            ],
            [
                'name' => $currentUser->name,
                'company_id' => $currentUser->current_company_id,
                'credit_balance' => $request['credit_balance'] ?? 0.00,
                'user_id' => $currentUser->id,
                'current_subscription_plan_id' => $request['subscription_plan_id'],
            ]
        );
    }

    protected function changePlan($request)
    {
        $currentUser = auth()->user();
        $subscriber = Subscriber::where('company_id', $currentUser->current_company_id)
            ->where('user_id', $currentUser->id)
            ->first();

        $plan = SubscriptionPlan::find($request['subscription_plan_id']);

        $currentSubscriptionAmount = $subscriber->subscriptionHistory->plan_amount;

        $newPlanAmount = $request['duration'] === SubscriptionPlanDurationEnums::MONTHLY->value ? $plan->monthly_fee : $plan->yearly_fee;

        if ($currentSubscriptionAmount < $newPlanAmount) {
            return $this->handleUpgrade();
        }

        return $this->handleDowngrade();
    }

    protected function createSubscriptionPaymentMethod($request)
    {
        return SubscriptionPaymentMethod::firstOrCreate(
            [
                'card_number' => $request['card_number'],
            ],
            [
                'card_number' => $request['card_number'],
                'name' => $request['name'],
                'expiry_date' => $request['expiry_date'],
                'cvv' => $request['cvv']
            ]
        );
    }
}
