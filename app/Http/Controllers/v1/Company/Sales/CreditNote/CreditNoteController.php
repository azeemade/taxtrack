<?php

namespace App\Http\Controllers\v1\Company\Sales\CreditNote;

use App\Exceptions\BadRequestException;
use App\Helpers\Posting\CreditNoteMultipleLinePosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\CreditNotes\CreateCreditNoteRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
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

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->creditNoteService->list($request);

            if ($request->export) {
                return $this->creditNoteService->export($records, $request->export);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function store(CreateCreditNoteRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->creditNoteService->create($request->validated());

            (new CreditNoteMultipleLinePosting())
                ->syncForCreditNote(
                    $record,
                    (int)$record->company_id,
                    (int)($record->created_by ?? null),
                    $request->validated()['invoices'] ?? null
                );


            // Recalculate affected invoices (only if issued; skip drafts)
            if ($record->status === \App\Enums\FinancialDocumentStatusEnums::ISSUED->value) {
                $invoiceIds = DB::table('credit_note_invoices')
                    ->where('credit_note_id', $record->id)
                    ->pluck('invoice_id')
                    ->unique();

                foreach ($invoiceIds as $invId) {
                    CreditNoteMultipleLinePosting::recalcInvoiceBalance((int)$invId);
                }
            }

            DB::commit();
            return JsonResponser::send(false, 'Credit note issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
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


    /**
     * Update the specified resource in storage.
     */
    public function update(CreateCreditNoteRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            // Invoices affected BEFORE update (so we can recalc ones that get de-allocated)
            $oldInvoiceIds = DB::table('credit_note_invoices')
                ->where('credit_note_id', (int)$id)
                ->pluck('invoice_id')
                ->unique();

            $payload = $request->validated();

            // Update the credit note (your service should also update pivot rows)
            $record = $this->creditNoteService->update([
                ...$payload,
                'id' => (int)$id,
            ]);

            // Post journal (pass payload items as fallback in case pivots aren't yet persisted)
            (new CreditNoteMultipleLinePosting())
                ->syncForCreditNote(
                    $record,
                    (int)$record->company_id,
                    (int)($record->created_by ?? null),
                    $payload['invoices'] ?? null
                );

            $invoiceIds = $this->creditNoteService->uniqueInvoices((int)$record->id, $oldInvoiceIds);
            foreach ($invoiceIds as $invId) {
                CreditNoteMultipleLinePosting::recalcInvoiceBalance((int)$invId);
            }

            DB::commit();
            return JsonResponser::send(false, 'Credit note updated successfully', $record, \Symfony\Component\HttpFoundation\Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    // public function update(CreateCreditNoteRequest $request, $id)
    // {
    //     try {
    //         DB::beginTransaction();
    //         $record = $this->creditNoteService->update([...$request->validated(), "id" => $id]);

    //         (new CreditNoteMultipleLinePosting())
    //             ->syncForCreditNote($record, (int)$record->company_id, (int)($record->created_by ?? null));

    //         DB::commit();
    //         return JsonResponser::send(false, 'Credit note updated successfully', $record, Response::HTTP_OK);
    //     } catch (BadRequestException $e) {
    //         DB::rollBack();
    //         return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
    //     }
    // }
}
