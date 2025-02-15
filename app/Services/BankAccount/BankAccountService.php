<?php

namespace App\Services\BankAccount;

use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\BankAccount;
use App\Services\PaymentRecords\PaymentRecordService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class BankAccountService
{
    protected PaymentRecordService $paymentRecordService;
    public function __construct(
        PaymentRecordService $paymentRecordService
    ) {
        $this->paymentRecordService = $paymentRecordService;
    }

    public function bankAccountTypes()
    {
        return [
            'Current Account',
            'Savings Account',
            'Joint Account',
            'Salary Account',
            'Corporate Account',
            'Recurring Deposit Account',
            'Domiciliary Account',
            'Fixed Deposit Account',
            'Escrow Account',
            'Loan Account',
            'Other Account',
        ];
    }

    public function updateOrCreate($request)
    {
        $record = BankAccount::updateOrCreate(["id" => $request["id"] ?? null], [...$request]);

        $record->paymentMethods()->create([
            'referenceID' => $this->generateRefId()
        ]);

        return $record;
    }

    public function bankList($request)
    {
        $records = BankAccount::query()
            ->select('id', 'holder_name', 'account_number')
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('holder_name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('holder_name', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('account_number', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function viewBank($id)
    {
        $record = BankAccount::query()
            ->select(
                'id',
                'account_number',
                'bank_id',
                'holder_name',
                'currency_id',
            )
            ->with([
                'currency:id,name',
                'bank:id,name'
            ])
            ->find($id);

        if (!$record) {
            return throw new BadRequestException("Bank not found!", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function initiateBankAccountConnection()
    {
        $currentUser = auth()->user();
        $currentUser->sendOtp();
    }

    public function completeBankAccountConnection($request) //not completed
    {
        $currentUser = auth()->user();

        if (!$currentUser->verifyOtp($request['otp'])) {
            throw new BadRequestException("Invalid OTP", Response::HTTP_BAD_REQUEST);
        }
        $currentUser->clearOtp();

        $bankDetails = $this->connectToBankService($request);;

        // $record = BankAccount::create([
        //     'bank_id' => $request['bank_id'],
        //     'holder_name' => $bankDetails['holder_name'],
        //     'account_type' => $bankDetails['account_type'],
        //     'account_number' => $bankDetails['account_number'],
        //     'currency_id' => $bankDetails['currency_id'],
        //     'opening_balance' => $bankDetails['opening_balance'],
        //     'opening_balance_as_at' => $bankDetails['opening_balance_as_at'],
        //     'userID' => $request['userID'],
        //     'user_password' => $request['user_password'],
        //     'connected' => true,
        // ]);

        return $request;
    }

    public function toggleStatus($id)
    {
        $record = BankAccount::find($id);
        $record->update([
            'is_active' => !$record->is_active,
        ]);

        return $record;
    }

    public function delete($id)
    {
        $record = BankAccount::find($id);
        $record->delete();
    }

    public function bankTransactions($request)
    {
        $request->is_bank = true;
        return $this->paymentRecordService->list($request);
    }

    public function stats($request)
    {
        $records = BankAccount::query()
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            });

        return [
            'statement_balance' => 0, //(clone $records)->sum('invoice_value'),
            'tax_track_balance' => 0, //(clone $records)->where('status', FinancialDocumentStatusEnums::OVERDUE)->sum('invoice_value'),
            'total_inflow' => 0, //(clone $records)->get()->sum('total_amount_paid'),
            'total_outflow' => 0 //(clone $records)->get()->sum('total_amount_paid'),
        ];
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Transaction Date', 'Transaction value', 'Recorded By', 'Transaction Type'];
        $records = $records->map(function ($record) {
            return [
                // Carbon::parse($record->due_date)->toFormattedDayDateString(),
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'bank_transactions_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'bank_transactions_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    protected function connectToBankService($request) //not completed
    {
        return [];
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PaymentMethod',
            "modelField" => 'referenceID',
            "prefix" => 'PM-',
            "idLength" => 4,
        ]);
    }
}
