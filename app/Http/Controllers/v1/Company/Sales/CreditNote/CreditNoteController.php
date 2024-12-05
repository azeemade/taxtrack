<?php

namespace App\Http\Controllers\v1\Company\Sales\CreditNote;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\CreditNotes\CreateCreditNoteRequest;
use App\Responser\JsonResponser;
use App\Services\CreditNotes\CreditNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CreditNoteController extends Controller
{

    protected CreditNoteService $creditNoteService;

    public function __construct(CreditNoteService $creditNoteService)
    {
        $this->creditNoteService = $creditNoteService;
    }

    public function store(CreateCreditNoteRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->creditNoteService->create($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Credit note issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function show(int $id)
    {
        try {
            $record = $this->creditNoteService->view($id);
            
            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
