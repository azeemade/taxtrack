<?php

namespace App\Services\PaymentRecords;

use App\Enums\DocumentableModelEnums;
use App\Enums\PaymentStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\PaymentMethod;
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
            ->when($request->id, fn($query) => $query->where('recordable_id', $request->id))
            ->select([
                'id',
                'recordable_id',
                'recordable_type',
                'paymentID',
                'paid_on',
                'amount_paid',
                'amount_due',
                'payment_type',
                'status',
                'payment_method_id'
            ])
            ->with([
                'recordable:id,vendor_id,vendor_billID,share_status' => [
                    'vendor:id,vendor_name,referenceID'
                ]
            ])
            ->when($request->sort_by, function ($query) use ($request) {
                switch ($request->sort_by) {
                    case 'alphabetically':
                        return $this->orderByVendorName($query);
                    case 'date_ascending':
                        return $query->orderBy('paid_on', 'asc');
                    case 'date_descending':
                        return $query->orderBy('paid_on', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->bank_account_id, function ($query) use ($request) {
                return $query->where('methodable_id', $request->bank_account_id);
            })
            ->when($request->is_card, function ($query) {
                return $query->whereRelation('paymentMethod', 'methodable_type', 'App\Models\CardAccount')
                    ->with(
                        [
                            'paymentMethod:id,methodable_id,methodable_type' => [
                                'methodable:id,holder_name,issuing_bank_id,card_brand_id,issuer_number,expiration_date' => [
                                    'cardBrand:id,name',
                                    'bank:id,name'
                                ]
                            ]
                        ]
                    );
            })
            ->when($request->is_bank, function ($query) {
                return $query->whereRelation('paymentMethod', 'methodable_type', 'App\Models\BankAccount')
                    ->with(
                        [
                            'paymentMethod:id,methodable_id,methodable_type' => [
                                'methodable:id,holder_name,bank_id' => [
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

    public function paymentMethods($request, $method)
    {
        $limit = $request['limit'] ?? 10;
        $paginate = $request['paginate'] ?? false;

        $records = PaymentMethod::query()
            ->select('id', 'methodable_id', 'methodable_type', 'referenceID')
            ->with([
                'methodable' => [
                    'bank:id,name'
                ]
            ])
            ->when($method, function ($query) use ($method) {
                if ($method == 'card') {
                    return $query->with([
                        'methodable.cardBrand:id,name'
                    ])
                        ->where('methodable_type', 'App\Models\CardAccount');
                }
                return $query->where('methodable_type', 'App\Models\BankAccount');
            })
            ->latest();

        if ($paginate) {
            return $records->paginate($limit);
        }
        return $records->get();
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

    protected function orderByVendorName($query)
    {
        $query->orderByRaw("
                    (
                        CASE recordable_type
                            WHEN 'App\\Models\\VendorBill' THEN (
                                SELECT vendors.vendor_name FROM vendors
                                JOIN vendor_bills ON vendors.id = vendor_bills.vendor_id
                                WHERE vendor_bills.id = payment_records.recordable_id
                                LIMIT 1
                            )
                            WHEN 'App\\Models\\PurchaseInvoice' THEN (
                                SELECT vendors.vendor_name FROM vendors
                                JOIN purchase_invoices ON vendors.id = purchase_invoices.vendor_id
                                WHERE purchase_invoices.id = payment_records.recordable_id
                                LIMIT 1
                            )
                            ELSE NULL
                        END
                    ) ASC
                ");
    }
}
