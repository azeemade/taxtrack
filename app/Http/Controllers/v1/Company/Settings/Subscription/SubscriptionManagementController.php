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

class SubscriptionManagementController extends Controller
{
    protected CompanySubscriptionService $companySubscriptionService;

    public function __construct(CompanySubscriptionService $companySubscriptionService)
    {
        $this->companySubscriptionService = $companySubscriptionService;
    }

    public function subscriptionHistory(SharedFilterRequest $request)
    {
        try {
            $records = $this->companySubscriptionService->subscriptionHistory($request);

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function viewHistory($id)
    {
        try {
            $record = $this->companySubscriptionService->viewHistory($id);

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function viewCurrentPlan()
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

    public function subscribeToPlan(CreateUpgradePlanRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->companySubscriptionService->subscribeToPlan($request);

            DB::commit();
            return JsonResponser::send(false, 'Record created successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
