<?php

namespace App\Services\Bills;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class BillsService
{
    protected SharedActionService $sharedActionServices;
    public function __construct(
        SharedActionService $sharedActionServices,
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function updateOrCreate($request)
    {
        $record = VendorBill::updateOrCreate(
            [
                "id" => $request["id"] ?? null
            ],
            [
                ...$request,
                'is_recurring' => $request['save_status'] == 'recur' ? true : false,
                'purchase_invoice_due_date' => PurchaseInvoice::find($request['purchase_invoice_id'])?->invoice_end_date,
                'vendor_billID' => $request['vendor_billID'] ?? $this->generateID(),
                'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
                'status' => $request['save_status'] === FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
            ]
        );

        if (isset($request["id"]) && $request["id"]) {
            $record->editLineItems($request['line_items']);
        } else {
            $record->addLineItems($request['line_items']);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }
    public function view(int $id)
    {
        $record = VendorBill::select(
            'id',
            'vendor_billID',
            'vendor_bill_due_date',
            'terms_and_conditions',
            'additional_comment',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'vendor_bill_total',
            'purchase_invoice_id',
            'vendor_id',
            'attachments',
            'is_recurring',
            'recurring_next_due_date',
            'recurring_start_date',
            'recurring_end_date',
            'repeat',
            'repeat_period',
        )
            ->with([
                'vendor:id,vendor_name,referenceID,primary_email',
                'purchaseInvoice:id,purchase_invoiceID,invoice_end_date',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Bill not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function lineItems(int $id)
    {
        $record = VendorBill::find($id);

        if (!$record) {
            throw new BadRequestException("Bill not found!", Response::HTTP_NOT_FOUND);
        }

        return $record->lineItems->load([
            'category:id,name'
        ]);
    }

    public function list($request)
    {
        $records = VendorBill::query()
            ->select(
                'id',
                'vendor_id',
                'vendor_billID',
                'created_at',
                'vendor_bill_total',
                'share_status',
                'is_recurring',
                'recurring_next_due_date',
                'recurring_start_date',
                'recurring_end_date',
                'repeat',
                'repeat_period',
                'created_at',
                'vendor_bill_due_date'
            )
            ->with([
                'vendor:id,vendor_name,referenceID,primary_email',
                'paymentRecords:id,amount_paid,amount_due,recordable_id,recordable_type',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
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
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->is_recurring, function ($query) use ($request) {
                return $query->where('is_recurring', $request->is_recurring);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('vendor_billID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('vendor', 'vendor_name', 'LIKE', '%' . $request->q . '%');
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

    public function export($records, $exportType)
    {
        $recordHeadings = ['Supplier details', 'Bill ID', 'Bill generated on', 'Bill total', 'Amount paid', 'Amount due', 'Status', 'Line item volume'];
        $records = $records->map(function ($record) {
            return [
                $record->vendor->vendor_name ?? null,
                $record->vendor_billID,
                Carbon::parse($record->created_at)->toFormattedDayDateString(),
                $record->vendor_bill_total ?? 0.00,
                $record->total_amount_paid ?? 0.00,
                $record->amount_due ?? 0.00,
                $record->status,
                count($record->lineItems) ?? 0
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'bills_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'bills_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function updateRecurringBill($request)
    {
        $record = VendorBill::find($request['id']);
        $record->update([
            'is_recurring' => true,
            'recurring_start_date' => $request['recurring_start_date'],
            'recurring_end_date' => $request['recurring_end_date'],
            'repeat' => $request['repeat'],
            'repeat_period' => $request['repeat_period'],
        ]);

        return $record;
    }

    public function generateID()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\VendorBill',
            "modelField" => 'vendor_billID',
            "prefix" => 'VB-',
            "idLength" => 6,
        ]);
    }
}
