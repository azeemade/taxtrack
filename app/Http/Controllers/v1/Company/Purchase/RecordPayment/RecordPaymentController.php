<?php

namespace App\Http\Controllers\v1\Company\Purchase\RecordPayment;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\Payment\RecordPaymentRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\PaymentRecords\PaymentRecordService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RecordPaymentController extends Controller
{
    protected PaymentRecordService $paymentRecordService;

    public function __construct(PaymentRecordService $paymentRecordService)
    {
        $this->paymentRecordService = $paymentRecordService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->paymentRecordService->list($request);

            if ($request->export) {
                return $this->paymentRecordService->export($records, $request->export);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function store(RecordPaymentRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->paymentRecordService->modify($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Payment recorded successfully', $record, Response::HTTP_OK);
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
            $record = $this->paymentRecordService->view($id);

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function update(RecordPaymentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->paymentRecordService->modify([...$request->validated(), 'id' => $id]);

            DB::commit();
            return JsonResponser::send(false, 'Payment record updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
