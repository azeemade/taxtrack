<?php

namespace App\Services\PaymentRecords;

use App\Enums\DocumentableModelEnums;
use App\Enums\PaymentStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\PaymentRecord;
use App\Models\Vendor;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class PaymentRecordService
{

    public function modify($request)
    {
        $record = PaymentRecord::updateOrCreate(
            [
                'id' => $request['id'] ?? null
            ],
            [
                ...$request,
                'payment_type' => 'debit',
                'recordable_id' => $request['model_id'],
                'recordable_type' => $this->matchRecordableType($request['model']),
                'paid_on' => $request['paid_on'] ?? now(),
                'paymentID' => $request['paymentID'] ?? $this->generatePaymentId()
            ]
        );

        $amountDue = $record->recordable->amount_due;
        $record->recordable()->update([
            'payment_status' => $amountDue > 0 ? PaymentStatusEnums::PARTIAL_PAYMENT->value : PaymentStatusEnums::FULL_PAYMENT->value
        ]);

        return $record;
    }
    public function view(int $id)
    {
        $record = PaymentRecord::select(
            'id',
            'paid_on',
            'amount_paid',
            'paymentID',
            'payment_method_id',
            'payment_proof',
            'attachments',
            'additional_notes'
        )
            ->with([
                'paymentMethod:id,methodable_id,methodable_type' => ['methodable:id,holder_name']
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function list($request)
    {
        $records = PaymentRecord::query()
            ->when($request->id, function ($query) use ($request) {
                return $query->where('recordable_id', $request->id);
            })
            ->select('id', 'recordable_id', 'recordable_type', 'paymentID', 'paid_on', 'amount_paid', 'amount_due', 'payment_type', 'status', 'payment_method_id')
            ->with([
                'recordable:id,vendor_id,vendor_billID,share_status' => ['vendor:id,vendor_name,referenceID']
            ])
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy(
                        Vendor::select('vendor_name')
                            ->whereColumn('vendor_id', 'vendors.id')
                            ->orderBy('vendor_name')
                            ->limit(1)
                    );
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('paid_on', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('paid_on', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->whereRelation('recordable', 'status', $request->status);
            })
            ->when($request->is_card, function ($query) {
                return $query->whereRelation('paymentMethod', 'methodable_type', 'App\Models\CardAccount')
                    ->with(
                        [
                            'paymentMethod:id,methodable_id,methodable_type' => [
                                'methodable:id,holder_name,issuing_bank_id,card_brand_id' => [
                                    'cardBrand:id,name',
                                    'bank:id,name'
                                ]
                            ]
                        ]
                    );
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('paymentID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('recordable', 'vendor_billID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('recordable.vendor', 'vendor_name', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('paid_on', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Supplier details', 'Payment ID', 'Paid on', 'Associated Bill ID', 'Amount paid', 'Amount due', 'Status'];
        $records = $records->map(function ($record) {
            return [
                $record->recordable->vendor->vendor_name ?? null,
                $record->paymentID,
                Carbon::parse($record->paid_on)->toFormattedDayDateString(),
                $record->recordable->vendor_billID ?? null,
                $record->amount_paid ?? 0.00,
                $record->amount_due ?? 0.00,
                $record->recordable->status ?? null
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payment_records_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payment_records_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function generatePaymentId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PaymentRecord',
            "modelField" => 'paymentID',
            "prefix" => 'PAY-',
            "idLength" => 4,
        ]);
    }

    protected function matchRecordableType(string $model)
    {
        return match (true) {
            $model === 'purchase_invoices' => DocumentableModelEnums::PURCHASE_INVOICE->value,
            $model === 'invoices' => DocumentableModelEnums::INVOICE->value,
            $model === 'vendor_bills' => DocumentableModelEnums::VENDOR_BILLS->value,
            default => throw new BadRequestException("Invalid model provided!", Response::HTTP_BAD_REQUEST),
        };
    }
}
