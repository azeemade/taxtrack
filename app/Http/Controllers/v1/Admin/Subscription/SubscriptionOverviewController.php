<?php

namespace App\Http\Controllers\v1\Admin\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionRefund;
use App\Responser\JsonResponser;
use App\Services\ManageSubscriptionOverviewServices\OverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionOverviewController extends Controller
{
    protected OverviewService $overviewService;

    public function __construct(OverviewService $overviewService)
    {
        $this->overviewService = $overviewService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {

            $overview = $this->overviewService->overview($request);
            $stats = $this->overviewService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->overviewService->export($overview);
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
            $record = SubscriptionHistory::where('id', $id)
                ->with('plan', 'subscriptionRefund', 'subscriptionCancellation')
                ->first();

            if (!$record) {
                return JsonResponser::send(false, 'Subscription not found.');
            }
            $userSubscriptionHistory = SubscriptionHistory::where('subscriber_id', $record->subscriber_id)
                ->with('plan', 'subscriptionRefund', 'subscriptionCancellation')
                ->get();

            $data = [
                "record" => $record,
                "userSubscriptionHistory" => $userSubscriptionHistory
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
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

            $record = $this->overviewService->approve($refund);

            DB::commit();
            return JsonResponser::send(false, 'Subscription refund approved successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
