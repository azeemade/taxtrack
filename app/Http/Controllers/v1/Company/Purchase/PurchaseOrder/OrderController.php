<?php

namespace App\Http\Controllers\v1\Company\Purchase\PurchaseOrder;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\PurchaseOrder\CreatePurchaseOrderRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }
    /** 
     * Duplicate invoice not done yet
     */

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->purchaseOrderService->list($request);

            if ($request->export) {
                return $this->purchaseOrderService->export($records, $request->export);
            }

            if ($request["paginate"]) {
                $stats = $this->purchaseOrderService->stats($request);
                $records = [
                    ...$stats,
                    'data' => $records
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateOrderNumber()
    {
        try {
            $record = $this->purchaseOrderService->generateRefId();
            return JsonResponser::send(false, 'Purchase order number generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function store(CreatePurchaseOrderRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->purchaseOrderService->updateOrCreate($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Purchase order issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $record = $this->purchaseOrderService->view($id);

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreatePurchaseOrderRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->purchaseOrderService->updateOrCreate([...$request->validated(), "id" => $id]);
            DB::commit();
            return JsonResponser::send(false, 'Purchase order updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
