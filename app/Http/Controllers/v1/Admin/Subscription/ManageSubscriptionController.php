<?php

namespace App\Http\Controllers\v1\Admin\Subscription;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
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
    public function index()
    {
        //
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
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
