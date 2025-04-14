<?php

namespace App\Http\Controllers\v1\Company\Budget;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Budget\CreateBudgetRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\Budget\BudgetService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{

    protected BudgetService $budgetService;
    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->budgetService->overview($request);

            if ($request->export) return $this->budgetService->export($records, $request->export);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateBudgetRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->budgetService->create($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Budget created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $record = $this->budgetService->view($id);

            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateBudgetRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->budgetService->update($request->validated(), $id);

            DB::commit();
            return JsonResponser::send(false, 'Budget updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            "status" => "required|in:active,draft,inactive"
        ]);

        try {
            DB::beginTransaction();

            $record = $this->budgetService->view($id);
            if(!$record){
                throw new BadRequestException("Budget not found", Response::HTTP_BAD_REQUEST);
            }
            
            $record->update([
                "status" => $request->status
            ]);

            DB::commit();
            return JsonResponser::send(false, 'Budget status modified successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $record = $this->budgetService->delete($id);
            return JsonResponser::send(false, 'Budget deleted successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function computePeriods(Request $request)
    {
        try {
            $record = $this->budgetService->computePeriods($request);
            return JsonResponser::send(false, 'Period computed successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
