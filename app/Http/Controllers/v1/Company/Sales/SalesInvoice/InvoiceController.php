<?php

namespace App\Http\Controllers\v1\Company\Sales\SalesInvoice;

use App\Enums\DocumentableModelEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\Invoices\CreateInvoiceRequest;
use App\Responser\JsonResponser;
use App\Services\Invoices\InvoiceService;
use App\Services\PaymentRecords\PaymentRecordService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{

    protected InvoiceService $invoiceService;
    protected PaymentRecordService $paymentRecordService;

    public function __construct(InvoiceService $invoiceService, PaymentRecordService $paymentRecordService)
    {
        $this->invoiceService = $invoiceService;
        $this->paymentRecordService = $paymentRecordService;
    }
    /** 
     * Duplicate invoice not done yet
     */

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $records = [];

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    public function generateInvoiceId()
    {
        try {
            $record = $this->invoiceService->generateInvoiceId();
            return JsonResponser::send(false, 'Invoice ID generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function store(CreateInvoiceRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->invoiceService->create($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Invoice issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Record invoice payment.
     * 
     * @param Illuminate\Http\Request Request
     * @param int $id
     * 
     * @return App\Responser\JsonResponser JsonResponser
     */
    public function recordPayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->paymentRecordService->create([
                ...$request->validated(),
                'recordable_id' => $id,
                'recordable_type' => DocumentableModelEnums::INVOICE->value,
                'paid_on' => $request->paid_on ?? now(),
                'paymentID' => $this->paymentRecordService->generatePaymentId()
            ]);

            DB::commit();
            return JsonResponser::send(false, 'Invoice issued successfully', $record, Response::HTTP_OK);
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
            $record = $this->invoiceService->view($id);
            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
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
}
