<?php

namespace App\Http\Controllers\v1\Company\Sales\SalesQuote;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\Quotes\CreateQuoteRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\Quotes\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    protected QuoteService $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
    }

    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->quoteService->list($request);
            if ($request->export) {
                return $this->quoteService->export($records, $request->export);
            }

            $stats = $this->quoteService->stats($request);
            $records = [
                ...$stats,
                'data' => $records
            ];

            if (!$request["paginate"]) {
                $records = $records["data"];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateQuoteId()
    {
        try {
            $record = $this->quoteService->generateQuoteId();
            return JsonResponser::send(false, 'Quote ID generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $record = $this->quoteService->view($id);

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function store(CreateQuoteRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->quoteService->updateOrCreate($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Quote generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function update(CreateQuoteRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->quoteService->updateOrCreate([...$request->validated(), "id" => $id]);
            DB::commit();
            return JsonResponser::send(false, 'Quote updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function convertToInvoice(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->quoteService->convertQuoteToInvoice($request->validated(), $id);
            DB::commit();
            return JsonResponser::send(false, 'Quote converted to invoice successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
            // return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
