<?php

namespace App\Http\Controllers\v1\Company\Purchase\PurchaseInvoice;

use App\Exceptions\BadRequestException;
use App\Helpers\Posting\PurchaseInvoicePosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\Payment\RecordPaymentRequest;
use App\Http\Requests\Company\Purchase\PurchaseInvoice\CreatePurchaseInvoiceRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\PurchaseInvoice\PurchaseInvoiceService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    protected PurchaseInvoiceService $purchaseInvoiceService;

    public function __construct(PurchaseInvoiceService $purchaseInvoiceService)
    {
        $this->purchaseInvoiceService = $purchaseInvoiceService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->purchaseInvoiceService->list($request);

            if ($request->export) {
                return $this->purchaseInvoiceService->export($records, $request->export);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateInvoiceNumber()
    {
        try {
            $record = $this->purchaseInvoiceService->generatePurchaseInvoiceId();
            return JsonResponser::send(false, 'Purchase invoice number generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function store(CreatePurchaseInvoiceRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->purchaseInvoiceService->updateOrCreate($request->validated());

        
            DB::commit();
            return JsonResponser::send(false, 'Purchase invoice issued successfully', $record, Response::HTTP_OK);
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
            $record = $this->purchaseInvoiceService->view($id);

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function matchPurchaseOrder(int $id, int $purchase_order_id)
    {
        try {
            $record = $this->purchaseInvoiceService->matchPurchaseOrder($id, $purchase_order_id);

            return JsonResponser::send(false, 'Purchase invoice matched successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreatePurchaseInvoiceRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->purchaseInvoiceService->updateOrCreate([...$request->validated(), "id" => $id]);

            (new PurchaseInvoicePosting())
                ->syncPurchaseInvoiceJournal($record, (int)$record->company_id, (int)($record->created_by ?? null));
                
            DB::commit();
            return JsonResponser::send(false, 'Purchase invoice updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function purchaseInvoiceLineItems(int $id)
    {
        try {
            $records = $this->purchaseInvoiceService->lineItems($id);

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function invoicesWithoutBillsAndShared()
    {
        try {
            //invoices that have no bill but shared to suppliers
            //this removes records from list of invoices to be converted to bills
            $request = new \stdClass();
            $request->paginate = false; // optional
            $request->filter_no_bill_shared = true; // custom flag for the service
            // $request->sort_by = "alphabetically";
            $request->status = "issued";
            // $request->q = "";
            $request->vendor_id = null;

            $records = $this->purchaseInvoiceService->dropdown($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
