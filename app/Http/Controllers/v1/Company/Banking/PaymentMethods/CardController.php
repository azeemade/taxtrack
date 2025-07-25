<?php

namespace App\Http\Controllers\v1\Company\Banking\PaymentMethods;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Responser\JsonResponser;
use App\Services\Card\CardService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Exceptions\BadRequestException;
use App\Http\Requests\Company\Banking\Cards\CreateCardRequest;
use App\Http\Requests\Shared\SharedFilterRequest;

class CardController extends Controller
{

    protected CardService $cardService;

    public function __construct(CardService $cardService)
    {
        $this->cardService = $cardService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->cardService->cardList($request);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function cardByBin(string $bin)
    {
        try {
            $record = $this->cardService->binDetails($bin);
            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCardRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();
            $record = $this->cardService->updateOrCreate($data);

            DB::commit();
            return JsonResponser::send(false, 'Card created successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $record = $this->cardService->viewCard($id);
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
    public function update(CreateCardRequest $request, string $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->cardService->updateOrCreate([...$request->validated(), "id" => $id]);
            DB::commit();
            return JsonResponser::send(false, 'Card updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function toggleStatus(int $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->cardService->toggleStatus($id);
            DB::commit();

            $message = $record->is_active
                ? 'Card activated successfully'
                : 'Card deactivated successfully';

            return JsonResponser::send(false, $message, $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->cardService->delete($id);
            DB::commit();
            return JsonResponser::send(false, 'Card deleted successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function cardTransactions(SharedFilterRequest $request)
    {
        try {
            $records = $this->cardService->cardTransactions($request);

            if ($request->export) {
                return $this->cardService->export($records, $request->export);
            }

            if ($request["paginate"]) {
                $stats = $this->cardService->stats($request);
                $records = [
                    ...$stats,
                    'data' => $records
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
