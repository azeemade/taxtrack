<?php

namespace App\Http\Controllers\v1\Admin\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\Subscriber;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionRefund;
use App\Responser\JsonResponser;
use App\Services\ManageSubscriberServices\SubscriberService;
use App\Services\ManageSubscriptionServices\CompanySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageSubscribersController extends Controller
{

    protected SubscriberService $subscriberService;
    protected CompanySubscriptionService $companySubscriptionService;

    public function __construct(SubscriberService $subscriberService, CompanySubscriptionService $companySubscriptionService)
    {
        $this->subscriberService = $subscriberService;
        $this->companySubscriptionService = $companySubscriptionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $overview = $this->subscriberService->overview($request);

            $stats = $this->subscriberService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->subscriberService->export($overview);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $record = Subscriber::with([
                'currentSubscriptionHistory:id,billed_per,subscription_plan_id,amount_paid,subscribed_at,end_date,paid_via,status,payment_type,subscriber_id' => ['plan:id,title']
            ])->find($id);

            if (!$record) {
                return JsonResponser::send(false, 'Subscriber not found.');
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function histories(Request $request, $id)
    {
        try {
            $records = $this->companySubscriptionService->subscriptionHistory($request, $id);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function refunds(Request $request, $id)
    {
        try {
            $records = $this->companySubscriptionService->refundRequests($request, $id);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function refund($id, $refund_id)
    {
        try {
            $records = $this->companySubscriptionService->viewRefund($refund_id);

            return JsonResponser::send(false, 'Record found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function cancellations(Request $request, $id)
    {
        try {
            $records = $this->companySubscriptionService->cancellationRequests($request, $id);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function cancellation($id, $cancellation_id)
    {
        try {
            $records = $this->companySubscriptionService->viewCancellation($cancellation_id);

            return JsonResponser::send(false, 'Record found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Display the subscribers subscription.
     */
    public function viewReceipts($id)
    {
        try {
            $record = SubscriptionHistory::where('id', $id)
                ->with('plan', 'subscriber.company')
                ->first();

            if (!$record) {
                return JsonResponser::send(false, 'Subscription not found.');
            }
            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function approveRefund($id)
    {
        try {
            DB::beginTransaction();

            $refund = SubscriptionRefund::where('subscription_history_id', $id)->first();

            if (!$refund) {
                return JsonResponser::send(false, 'Subscription refund not found.');
            }

            $record = $this->subscriberService->approve($refund);

            DB::commit();
            return JsonResponser::send(false, 'Subscription refund approved successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
