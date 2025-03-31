<?php

namespace App\Http\Controllers\v1\Company\Accounting\JournalOfEntry;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Accounting\JournalEntry\JournalEntryRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\ChartOfAccount\JournalEntryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JournalOfEntryController extends Controller
{
    protected JournalEntryService $journalEntryService;

    public function __construct(JournalEntryService $journalEntryService)
    {
        $this->journalEntryService = $journalEntryService;
    }

    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->journalEntryService->allJournalEntries($request);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function createJournalEntry(JournalEntryRequest $request)
    {
        try {
            $record = $this->journalEntryService->createJournalEntry($request);
            return JsonResponser::send(false, 'Account created successfully!', $record, Response::HTTP_CREATED);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function show($id)
    {
        try {
            $records = $this->journalEntryService->show($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function update(JournalEntryRequest $request, $id)
    {
        try {
            $records = $this->journalEntryService->updateAccount($request, $id);
            return JsonResponser::send(false, 'Record updated successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function delete($id)
    {
        try {
            $records = $this->journalEntryService->delete($id);
            return JsonResponser::send(false, 'Record deleted successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
