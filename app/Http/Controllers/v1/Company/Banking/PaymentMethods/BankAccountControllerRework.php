<?php

namespace App\Http\Controllers\v1\Company\Banking\PaymentMethods;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Accounting\ChartOfAccount\StoreBankRequest;
use App\Http\Requests\Company\Accounting\ChartOfAccount\UpdateBankRequest;
use App\Http\Requests\Company\Accounting\ChartOfAccount\UpdateChartOfAccountRequest;
use App\Http\Requests\Company\Banking\Banks\CreateBankConnectionRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\ChartOfAccount\ChartOfAccountService;
use App\Services\FinanceAccountEntry\FinanceAccountEntryService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BankAccountControllerRework extends Controller
{
    protected ChartOfAccountService $chartOfAccountService;
    protected FinanceAccountEntryService $financeAccountEntryService;

    public function __construct(ChartOfAccountService $chartOfAccountService, FinanceAccountEntryService $financeAccountEntryService)
    {
        $this->chartOfAccountService = $chartOfAccountService;
        $this->financeAccountEntryService = $financeAccountEntryService;
    }

    /**
     * Display a listing of the resource.
     */

    public function stats(SharedFilterRequest $request)
    {
        try {
            // System (from GL entries)
            $system = $this->financeAccountEntryService
                ->getSystemTotalBalanceAndFlow($request->start_date, $request->end_date);

            // Bank (from imported bank statement lines)
            // Optional: pass $request->bank_account_id if you want a single bank; null = all banks
            $bank = $this->financeAccountEntryService
                ->getBankStatementBalanceAndFlow($request->start_date, $request->end_date, $request->bank_account_id ?? null);

            $data = [
                // BANK (closing balance at end_date = opening + inflow - outflow)
                'bank_statement_balance' => $bank['closing_balance'],
                'bank_opening_balance'   => $bank['opening_balance'],
                'bank_total_inflow'      => $bank['total_inflow'],
                'bank_total_outflow'     => $bank['total_outflow'],

                // SYSTEM
                'system_balance'         => $system['total_system_balance'],
                'total_inflow'           => $system['total_inflow'],
                'total_outflow'          => $system['total_outflow'],
            ];

            return JsonResponser::send(false, 'Record(s) found successfully', $data, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    // public function stats(SharedFilterRequest $request)
    // {
    //     try {
    //         $bankStatementBalance = 0;
    //         $systemBalance = $this->financeAccountEntryService->getSystemTotalBalanceAndFlow($request->start_date, $request->end_date);

    //         $data = [
    //             'bank_statement_balance' => $bankStatementBalance,
    //             'system_balance' => $systemBalance['total_system_balance'],
    //             'total_inflow' => $systemBalance['total_inflow'],
    //             'total_outflow' => $systemBalance['total_outflow'],
    //         ];

    //         return JsonResponser::send(false, 'Record(s) found successfully', $data, Response::HTTP_OK);
    //     } catch (BadRequestException $e) {
    //         return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
    //     }
    // }


    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->chartOfAccountService->allCashAndBankAccounts($request);
            if ($request->export) return $records;
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function indexCardView(SharedFilterRequest $request)
    {
        try {
            $records = $this->chartOfAccountService->indexCardView($request);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBankRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->chartOfAccountService->createCashAndBankAccount($request);
            DB::commit();
            return JsonResponser::send(false, 'Bank account created successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $records = $this->chartOfAccountService->show($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBankRequest $request, string $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->chartOfAccountService->updateCashAndBankAccount($request, $id);
            DB::commit();
            return JsonResponser::send(false, 'Bank account updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function toggleStatus($id)
    {
        try {
            $records = $this->chartOfAccountService->toggleStatus($id);
            return JsonResponser::send(false, 'Record updated successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        try {
            $records = $this->chartOfAccountService->delete($id);
            return JsonResponser::send(false, 'Record deleted successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function bankTransactions(SharedFilterRequest $request)
    {
        try {
            $records = $this->bankAccountService->bankTransactions($request);

            if ($request->export) {
                return $this->bankAccountService->export($records, $request->export);
            }

            if ($request["paginate"]) {
                $stats = $this->bankAccountService->stats($request);
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


    /**
     * Display a listing of the resource.
     */
    public function accountTypes()
    {
        try {
            $record = $this->bankAccountService->bankAccountTypes();
            return JsonResponser::send(false, 'Record(s) found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function initiateConnection()
    {
        try {
            $this->bankAccountService->initiateBankAccountConnection();
            return JsonResponser::send(false, 'OTP sent successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function completeConnection(CreateBankConnectionRequest $request)
    {
        try {
            $record = $this->bankAccountService->completeBankAccountConnection($request->validated());
            return JsonResponser::send(false, 'Account connected successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
