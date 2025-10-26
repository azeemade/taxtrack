<?php

namespace App\Http\Controllers\v1\Company\Purchase\Bills;

use App\Enums\FinancialDocumentStatusEnums;
use App\Helpers\Posting\VendorBillPosting;
use App\Exceptions\BadRequestException;
use App\Helpers\Posting\BillVoidPosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\Bills\CreateBillsRequest;
use App\Http\Requests\Company\Purchase\CreateRecurringDocumentRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\VendorBill;
use App\Responser\JsonResponser;
use App\Services\Bills\BillsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BillsController extends Controller
{
    protected BillsService $billsService;

    public function __construct(BillsService $billsService)
    {
        $this->billsService = $billsService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->billsService->list($request);
            if ($request->export) {
                return $this->billsService->export($records, $request->export);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateBillsRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->billsService->updateOrCreate($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Bills created successfully', $record);
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
    public function show($id)
    {
        try {
            $record = $this->billsService->view($id);

            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateBillsRequest $request, string $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->billsService->updateOrCreate($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Bills updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateReference()
    {
        try {
            $record = $this->billsService->generateID();
            return JsonResponser::send(false, 'ID generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Void bill
     */
    public function voidBill($id)
    {
        try {
            DB::beginTransaction();

            $bill = $this->billsService->getBillById($id);
            if (!$bill) {
                return JsonResponser::send(true, 'Bill does not exist', null, Response::HTTP_NOT_FOUND);
            }

            // $bill->update([
            //     'status' => FinancialDocumentStatusEnums::VOID
            // ]);

            (new BillVoidPosting())->voidBill($bill, (int)$bill->company_id, (int)($bill->created_by ?? null));
            DB::commit();
            return JsonResponser::send(false, 'Bill has been voided successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (Exception $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], 400);
        }catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function recurringBill(CreateRecurringDocumentRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $this->billsService->updateRecurringBill([...$request->validated(), "id" => $id]);

            DB::commit();
            return JsonResponser::send(false, 'Bill has been configured to recurring successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
