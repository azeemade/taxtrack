<?php

namespace App\Http\Controllers\v1\Company\Purchase\DebitNote;

use App\Exceptions\BadRequestException;
use App\Helpers\Posting\DebitNotePosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\DebitNote\CreateDebitNoteRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\DebitNotes\DebitNoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DebitNoteController extends Controller
{
    protected DebitNoteService $debitNoteService;

    public function __construct(DebitNoteService $debitNoteService)
    {
        $this->debitNoteService = $debitNoteService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $record = $this->debitNoteService->list($request);
            return JsonResponser::send(false, 'Debit note(s) retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateDebitNoteRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->debitNoteService->create($request->validated());

            (new DebitNotePosting())
                ->syncForDebitNote($record, (int)$record->company_id, (int)($record->created_by ?? null));

            DB::commit();
            return JsonResponser::send(false, 'Debit note created successfully', $record, Response::HTTP_OK);
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
            $record = $this->debitNoteService->view($id);
            return JsonResponser::send(false, 'Debit note retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateDebitNoteRequest $request, int $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->debitNoteService->update([...$request->validated(), "id" => $id]);

            (new DebitNotePosting())
                ->syncForDebitNote($record, (int)$record->company_id, (int)($record->created_by ?? null));

            DB::commit();
            return JsonResponser::send(false, 'Debit note updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
