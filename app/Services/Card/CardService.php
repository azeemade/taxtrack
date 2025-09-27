<?php

namespace App\Services\Card;

use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\CardAccount;
use App\Services\PaymentRecords\PaymentRecordService;
use App\Services\ThirdPartyApi\CardServiceApi;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class CardService
{
    protected PaymentRecordService $paymentRecordService;
    public function __construct(
        PaymentRecordService $paymentRecordService
    ) {
        $this->paymentRecordService = $paymentRecordService;
    }
    public function binDetails(string $bin)
    {
        $response = CardServiceApi::cardByBin($bin);
        if (!$response) {
            return ["status" => 'FAILED', "message" => 'Failed to get card details'];
        }

        return [
            'status' => 'SUCCESS',
            'card_type' => $response['Scheme'],
            'country' => $response['Country']['Name'],
            'issuing_bank' => $response['Issuer'],
        ];
    }

    public function updateOrCreate($request)
    {
        $request['created_by'] = auth()->user()->id;
        $request['company_id'] = auth()->user()->company->id;

        $record = CardAccount::updateOrCreate(["id" => $request["id"] ?? null], [...$request]);

        if (!isset($request["id"])) {
            $record->paymentMethods()->create([
                'referenceID' => $this->generateRefId(),
                'created_by' => auth()->user()->id,
                'company_id' => auth()->user()->company->id,
            ]);
        }

        return $record;
    }

    public function cardList($request)
    {
        $records = CardAccount::query()
            ->select('id', 'expiration_date', 'holder_name', 'issuer_number', 'card_brand_id')
            ->with([
                'cardBrand:id,name',
            ])
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
                    ->orWhere('issuer_number', 'LIKE', '%' . $request->q . '%');
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

    public function viewCard($id)
    {
        $record = CardAccount::query()
            ->select(
                'id',
                'expiration_date',
                'holder_name',
                'issuer_number',
                'card_brand_id',
                'currency_id',
                'billing_address',
                'billing_postal_code',
                'issuing_bank_id',
                'billing_country_id',
                'cvv',
                'is_active'
            )
            ->with([
                'cardBrand:id,name',
                'billingCountry:id,name',
                'currency:id,name',
                'bank:id,name'
            ])
            ->find($id);

        if (!$record) {
            return throw new BadRequestException("Card not found!", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function toggleStatus($id)
    {
        $record = CardAccount::find($id);
        $record->update([
            'is_active' => !$record->is_active,
        ]);

        return $record;
    }

    public function delete($id)
    {
        $record = CardAccount::find($id);
        $record->paymentMethods()->delete();
        $record->delete();
    }

    public function cardTransactions($request)
    {
        $request->is_card = true;
        return $this->paymentRecordService->list($request);
    }



    public function stats($request)
    {
        $records = CardAccount::query()
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            });

        return [
            'card_transaction_value' => 0, //(clone $records)->sum('invoice_value'),
            'successful_transaction' => 0, //(clone $records)->where('status', FinancialDocumentStatusEnums::OVERDUE)->sum('invoice_value'),
            'failed_transaction' => 0 //(clone $records)->get()->sum('total_amount_paid'),
        ];
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Transaction ID', 'Card Details', 'Transaction value', 'Date', 'Status'];
        $records = $records->map(function ($record) {
            return [
                // Carbon::parse($record->due_date)->toFormattedDayDateString(),
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.csv', \Maatwebsite\Excel\Excel::CSV);
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
