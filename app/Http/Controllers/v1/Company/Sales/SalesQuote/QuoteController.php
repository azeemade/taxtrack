<?php

namespace App\Http\Controllers\v1\Company\Sales\SalesQuote;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\Quotes\CreateQuoteRequest;
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

    public function store(CreateQuoteRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->quoteService->create($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Quote issued successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
