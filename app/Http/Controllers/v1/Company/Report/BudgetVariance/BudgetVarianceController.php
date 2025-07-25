<?php

namespace App\Http\Controllers\v1\Company\Report\BudgetVariance;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Services\Budget\BudgetService;
use App\Services\FinanceAccountType\FinanceAccountTypeService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class BudgetVarianceController extends Controller
{
    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    public function index(SharedFilterRequest $request)
    {
        try {
            $data = $this->budgetService->getBudgetVarianceReport(
                $request->budget_id,
                $request->start_date,
                $request->end_date
            );
            return JsonResponser::send(false, 'Budget variance report generated successfully', $data, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JsonResponser::send(true, 'Budget not found', null, 404);
        } catch (\Throwable $e) {
            logger($e);
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode() ?: 500, $e);
        }
    }
}