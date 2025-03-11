<?php

namespace App\Http\Controllers\v1\Admin\Subscription;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Http\Requests\StoreHistoryRequest;
use App\Http\Requests\StorePlanRequest;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRefund;
use App\Responser\JsonResponser;
use App\Services\ManageSubscriptionServices\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManageSubscriptionController extends Controller
{

    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {

            $overview = $this->subscriptionService->overview($request);
            $stats = $this->subscriptionService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->subscriptionService->export($overview);
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
    public function store(StorePlanRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->subscriptionService->create($request->all());

            DB::commit();
            return JsonResponser::send(false, 'Subscription plan created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function createHistory(StoreHistoryRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->subscriptionService->createHistory($request->all());

            DB::commit();
            return JsonResponser::send(false, 'Subscription history created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $record = SubscriptionPlan::where('id', $id)
                ->with('subscriptionPlanFeature')
                ->first();

            if (!$record) {
                return JsonResponser::send(false, 'Plan not found.');
            }

            // Group subscription functionalities by module_id
            $groupedFunctionalities = [];
            foreach ($record->subscriptionFunctionality as $functionality) {
                $moduleId = $functionality->module_id;

                // Initialize group if not exists
                if (!isset($groupedFunctionalities[$moduleId])) {
                    $groupedFunctionalities[$moduleId] = [
                        'module' => $functionality->moduleFunctionality->module,
                        'functionalities' => []
                    ];
                }

                // Add the module functionality to the grouped list
                $groupedFunctionalities[$moduleId]['functionalities'][] = $functionality->moduleFunctionality;
            }

            // Transform the grouped functionalities into a simpler array structure
            $record->grouped_subscription_functionality = array_values($groupedFunctionalities);

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Display the subscribers subscription.
     */
    public function showSubscriberSubscription($id)
    {
        try {
            $record = SubscriptionHistory::where('id', $id)
                ->with('plan', 'subscriptionRefund', 'subscriptionCancellation')
                ->first();

            if (!$record) {
                return JsonResponser::send(false, 'Subscription not found.');
            }
            $userSubscriptionHistory = SubscriptionHistory::where('subscriber_id', $record->subscriber_id)
                ->with('plan:id,title')
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
     * Update the specified resource in storage.
     */
    public function update(StorePlanRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $plan = SubscriptionPlan::where('id', $id)->first();

            if (!$plan) {
                return JsonResponser::send(false, 'Plan not found.');
            }

            $record = $this->subscriptionService->update($request->all(), $plan);

            DB::commit();
            return JsonResponser::send(false, 'Plan updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function toggleStatus($id)
    {
        try {
            DB::beginTransaction();

            $plan = SubscriptionPlan::where('id', $id)->first();

            if (!$plan) {
                return JsonResponser::send(false, 'Plan not found.');
            }

            $record = $this->subscriptionService->toggle($plan);

            DB::commit();
            return JsonResponser::send(false, 'Plan updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
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

            $record = $this->subscriptionService->approve($refund);

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
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $plan = SubscriptionPlan::where('id', $id)->first();

            if (!$plan) {
                return JsonResponser::send(false, 'Plan not found.');
            }

            $this->subscriptionService->delete($plan);

            DB::commit();
            return JsonResponser::send(false, 'Plan deleted successfully', null);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function moduleFunctionalities()
    {
        try {
            DB::beginTransaction();

            $record = $this->subscriptionService->module();

            DB::commit();
            return JsonResponser::send(false, 'Module functionalities found successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
