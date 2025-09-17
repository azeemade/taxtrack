<?php

namespace App\Http\Controllers\v1\Company\Report\Reconciliation;

use App\Exceptions\BadRequestException;
use App\Exports\Report\AccountSummaryExport;
use App\Exports\Report\BankReconciliationLinesExport;
use App\Exports\Report\BankReconciliationSummaryExport;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Imports\Banking\BankStatementImport;
use App\Services\AccountReconciliation\AccountReconciliationService;
use App\Services\Budget\BudgetService;
use App\Services\FinanceAccountType\FinanceAccountTypeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ReconciliationController extends Controller
{

    protected FinanceAccountTypeService $financeAccountTypeService;
    protected BudgetService $budgetService;
    protected AccountReconciliationService $accountReconciliationService;

    public function __construct(
        FinanceAccountTypeService $financeAccountTypeService,
        BudgetService $budgetService,
        AccountReconciliationService $accountReconciliationService,
    ) {
        $this->financeAccountTypeService = $financeAccountTypeService;
        $this->budgetService = $budgetService;
        $this->accountReconciliationService = $accountReconciliationService;
    }

    public function accountSummary(Request $request)
    {
        try {
            $budgetId = $request->budget_id;
            $accountId = $request->account_id;
            $periodType = $request->period_type; // Possible period_type values: specific_date, this_month, last_month, this_quarter, last_quarter, this_year_to_current_date, this_quarter_to_current_date, this_month_to_current_date, quarter_end, year_end, financial_year_end, default
            $dateInput = $request->date_input;
            // "period_type": "custom_range",
            // "date_input": {
            //     "start_date": "2025-01-01",
            //     "end_date": "2025-09-07"
            // }

            // "period_type": "specific_date",
            // "date_input": "2025-09-07",

            if (is_null($budgetId) || is_null($accountId)) {
                return JsonResponser::send(true, "Please select budget and account", null, 400);
            }

            $records = $this->budgetService->accountSummary($budgetId, $accountId, $request);

            if ($request->export) {
                return Excel::download(
                    new AccountSummaryExport($records),
                    'account_summary_' . now()->format('Ymd_His') . '.xlsx'
                );
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
            // return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function trialBalance(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->trialBalanceReport($request);

            if ($request->export) {
                return $records;
            }
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }





    public function allBankStatements(SharedFilterRequest $request)
    {
        try {
            $records = $this->accountReconciliationService->bankStatements($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function allAccountTransactions(SharedFilterRequest $request)
    {
        try {
            $records = $this->accountReconciliationService->allAccountTransactions($request);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function uploadBankStatement(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'file' => 'required|mimes:xlsx,xls,csv',
                'account_id' => [
                    'required',
                    Rule::exists('finance_chart_of_accounts', 'id')->where(function ($query) {
                        $query->where('company_id', auth()->user()->current_company_id);
                    }),
                ],
            ]);

            if ($validator->fails()) {
                return JsonResponser::send(true, 'Incorrect file format uploaded', $validator->errors());
            }


            // Store the uploaded file temporarily
            $filePath = $request->file('file')->store('temp'); // Stores in storage/app/temp
            $user = auth()->user();
            // ProcessBankStatementImport::dispatch(
            //     $request->account_id,
            //     $filePath,
            //     $user->current_company_id,
            //     $user->id
            // );

            Excel::import(new BankStatementImport($request->account_id, $user->current_company_id, $user->id), $request->file('file'));

            DB::commit();
            return JsonResponser::send(false, 'Bank Statement successfully uploaded', [], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function downloadBankStatementTemplate(Request $request)
    {
        try {
            $data = $this->accountReconciliationService->downloadBankStatementTemplate($request);
            return Excel::download(new BankStatementTemplateExport($data), 'bank_statement_template.xlsx');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function getBankReconciliationSummary(Request $request)
    {
        try {
            $data = $this->accountReconciliationService->getBankReconciliationSummary($request);

            if ($request->export === 'excel') {
                return Excel::download(new BankReconciliationSummaryExport($data), 'bank_reconciliation_summary.xlsx');
            } elseif ($request->export === 'pdf') {
                $pdf = PDF::loadView('reports.bank_reconciliation_summary', ['data' => $data]);
                return $pdf->download('bank_reconciliation_summary.pdf');
            }

            return JsonResponser::send(false, 'Bank Reconciliation Summary fetched successfully', $data, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
            // return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function getBankReconciliationLines(Request $request)
    {
        try {
            $data = $this->accountReconciliationService->getBankReconciliationLines($request);

            if ($request->export === 'excel') {
                return Excel::download(new BankReconciliationLinesExport($data), 'bank_reconciliation_lines.xlsx');
            } elseif ($request->export === 'pdf') {
                $pdf = PDF::loadView('reports.bank_reconciliation_lines', ['data' => $data]); // Assume view exists
                return $pdf->download('bank_reconciliation_lines.pdf');
            }

            return JsonResponser::send(false, 'Bank Reconciliation Lines fetched successfully', $data, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    
    public function reconcileLine(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bank_statement_id' => 'required|exists:finance_bank_statements,id',
                'matches' => 'required|array|min:1',
                'matches.*.account_entry_id' => 'required|exists:finance_account_entries,id',
                'matches.*.matched_amount' => 'required|numeric|min:0.01',
            ]);

            if ($validator->fails()) {
                throw new BadRequestException($validator->errors()->first());
            }

            $result = $this->accountReconciliationService->reconcileBankLine($request->bank_statement_id, $request->matches, auth()->id());
            return JsonResponser::send(false, 'Reconciliation processed', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }







    public function buildAndSaveReconciliationRecords(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'account_id' => 'required|exists:finance_chart_of_accounts,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'period_type' => 'nullable|in:specific_date,this_month,quarter_end,year_end,financial_year_end,last_month,this_quarter,last_quarter,this_year_to_current_date,this_quarter_to_current_date,this_month_to_current_date,quarter_end,year_end,financial_year_end',
                'date_input' => 'nullable|date',
                'batch_id' => 'nullable|string',
                'replace' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                throw new BadRequestException($validator->errors()->first());
            }

            $result = $this->accountReconciliationService->buildReconciliationPreview($request);
            return JsonResponser::send(false, 'Reconciliation processed', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            // return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }



    


    public function getReconciliationRun(Request $request, $runId)
    {
        try {
            $result = $this->accountReconciliationService->getReconciliationRun($request, $runId);
            return JsonResponser::send(false, 'Reconciliation Run fetched successfully', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function listReconciliationRuns(Request $request)
    {
        try {
            $result = $this->accountReconciliationService->listReconciliationRuns($request);
            return JsonResponser::send(false, 'Reconciliation Runs fetched successfully', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }   


    //Not needed currently
    public function listReconciliationRecords(Request $request)
    {
        try {
            $result = $this->accountReconciliationService->listReconciliationRecords($request);
            return JsonResponser::send(false, 'Reconciliation Records fetched successfully', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function getReconciliationSummaryFromRecords(Request $request)
    {
        try {
            $result = $this->accountReconciliationService->getReconciliationSummaryFromRecords($request);
            return JsonResponser::send(false, 'Reconciliation Summary fetched successfully', $result, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


}
