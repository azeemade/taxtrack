<?php

namespace App\Http\Controllers\v1\Company\Settings\DocumentSettings;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Settings\DocumentSettings\AddDocumentRemainderRequest;
use App\Responser\JsonResponser;
use App\Services\DocumentSettings\DocumentSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceRemainderController extends Controller
{
    protected DocumentSettingsService $documentSettingsService;

    public function __construct(DocumentSettingsService $documentSettingsService)
    {
        $this->documentSettingsService = $documentSettingsService;
    }

    public function index()
    {
        try {
            $records = $this->documentSettingsService->invoiceRemainderList();

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function view($id)
    {
        try {
            $record = $this->documentSettingsService->invoiceRemainder($id);

            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function modify(AddDocumentRemainderRequest $request)
    {
        try {
            $record = $this->documentSettingsService->addInvoiceRemainder($request->validated());

            return JsonResponser::send(false, 'Record modified successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
