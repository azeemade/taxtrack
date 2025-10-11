<?php

namespace App\Http\Controllers\v1\Company\Settings\Subscription;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Settings\Subscription\CreateUpgradePlanRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\ManageSubscriptionServices\CompanySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanController extends Controller
{
    protected CompanySubscriptionService $companySubscriptionService;

    public function __construct(CompanySubscriptionService $companySubscriptionService)
    {
        $this->companySubscriptionService = $companySubscriptionService;
    }

    public function view()
    {
        try {
            $record = $this->companySubscriptionService->currentPlan();

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function allPlans()
    {
        try {
            $records = $this->companySubscriptionService->plans();

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function planBreakdown(Request $request)
    {
        try {
            $records = $this->companySubscriptionService->calculateSubscriptionCosts(
                $request->plan_amount,
                $request->seat_amount,
                false,
                $request->additional_users_count
            );

            return JsonResponser::send(false, 'Record retrieved successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function create(CreateUpgradePlanRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->companySubscriptionService->subscribeToPlan($request);

            DB::commit();
            return JsonResponser::send(false, 'Record created successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode(), $e);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function markAsPaid(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|string|exists:subscription_histories,provider_subscription_id',
        ]);
        try {
            $this->companySubscriptionService->markAsPaid($request->subscription_id);
            return JsonResponser::send(false, 'Subscription created successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode(), $e);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
