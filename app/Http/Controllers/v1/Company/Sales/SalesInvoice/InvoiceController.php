<?php

namespace App\Http\Controllers\v1\Company\Sales\SalesInvoice;

use App\Enums\FinancialDocumentStatusEnums;
use App\Exceptions\BadRequestException;
use App\Helpers\Posting\InvoiceAdjustments;
use App\Helpers\Posting\PaymentPosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateBadDebtRequest;
use App\Http\Requests\Company\Purchase\Payment\RecordPaymentRequest;
use App\Http\Requests\Company\Sales\Invoices\CreateInvoiceRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\Invoice;
use App\Responser\JsonResponser;
use App\Services\Invoices\InvoiceService;
use App\Services\PaymentRecords\PaymentRecordService;
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
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->invoiceService->list($request);

            if ($request->export) {
                return $this->invoiceService->export($records, $request->export);
            }

            if ($request["paginate"]) {
                $stats = $this->invoiceService->stats($request);
                $records = [
                    ...$stats,
                    'data' => $records
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
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
            $record = $this->invoiceService->updateOrCreate($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Invoice issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
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
    public function recordPayment(RecordPaymentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $invoice = \App\Models\Invoice::findOrFail($id);

            if ($invoice->status !== 'issued') {
                return JsonResponser::send(true, 'You cannot record payment for an invoice that is not issued.', [], Response::HTTP_BAD_REQUEST);
            }

            // Ensure model/model_id are set to this invoice id
            $payload = array_merge($request->validated(), [
                'model'    => 'invoices',
                'model_id' => $id,
            ]);

            // Persist / upsert payment record (make sure it stores bank_account_id too)
            $record = $this->paymentRecordService->modify($payload);

            // ✅ Reduce the customer current balance by amount received
            $record->recordable->customer->decrement('current_balance', $record->amount_paid);

            // Idempotent: creates/updates the SAME journal for this payment record
            (new PaymentPosting())
                ->syncForPaymentRecord($record);

            DB::commit();
            return JsonResponser::send(false, 'Payment recorded successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
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
     * Update the specified resource in storage.
     */
    public function update(CreateInvoiceRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->invoiceService->updateOrCreate([...$request->validated(), "id" => $id]);
            DB::commit();
            return JsonResponser::send(false, 'Invoice updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Write off as bad debt
     */
    public function writeOffInvoice(CreateBadDebtRequest $request, Invoice $invoice)
    {
        try {
            DB::beginTransaction();

            //block write-off if invoice is already paid
            if (in_array($invoice->payment_status, ['paid'])) {
                throw new BadRequestException('Only unpaid or partially paid invoices can be written off', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $invoice->badDebt()->create($request->validated());

            //Post Bad Debt - Account Entries
            (new InvoiceAdjustments())
                ->postBadDebt($invoice, (float)$request->input('amount'), $request->input('note'));

            DB::commit();
            return JsonResponser::send(false, 'Customer invoice has been successfully written off', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Void invoice
     */
    public function voidInvoice(Invoice $invoice)
    {
        try {
            DB::beginTransaction();

            //block void if invoice is already paid or partially paid
            if (in_array($invoice->payment_status, ['paid', 'partially_paid'])) {
                throw new BadRequestException('Only unpaid invoices can be voided', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $invoice->update([
                'status' => FinancialDocumentStatusEnums::VOID
            ]);

            //Void Invoice - Account Entries
            (new InvoiceAdjustments())->voidInvoice($invoice);

            DB::commit();
            return JsonResponser::send(false, 'Customer invoice has been voided successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
