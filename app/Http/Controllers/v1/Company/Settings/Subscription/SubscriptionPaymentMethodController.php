<?php

namespace App\Http\Controllers\v1\Company\Settings\Subscription;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\ManageSubscriptionServices\CompanySubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionPaymentMethodController extends Controller
{
    protected CompanySubscriptionService $companySubscriptionService;

    public function __construct(CompanySubscriptionService $companySubscriptionService)
    {
        $this->companySubscriptionService = $companySubscriptionService;
    }


    public function paymentMethods(SharedFilterRequest $request)
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
}
