<?php

namespace App\Http\Controllers\v1\Company\Settings\Subscription;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\SubscriptionPaymentMethod;
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


    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->companySubscriptionService->subscriptionCards($request);

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->companySubscriptionService->delete($id);

            return JsonResponser::send(false, 'Record deleted successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
