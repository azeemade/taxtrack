<?php

namespace App\Services\DebitNotes;

use App\Enums\DocumentableModelEnums;
use App\Enums\FinancialDocumentStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\DebitNote;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class DebitNoteService
{
    protected SharedActionService $sharedActionServices;

    public function __construct(
        SharedActionService $sharedActionServices
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function list($request)
    {
        $records = DebitNote::query()
            ->select('id', 'date_issued', 'noteID', 'vendor_id', 'status', 'share_status')
            ->with([
                'vendor:id,vendor_name,primary_email',
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
            ->when($request->q, function ($query) use ($request) {
                return $query->where('noteID', 'LIKE', '%' . $request->q . '%')
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

    public function create($request)
    {
        $record = DebitNote::create([
            ...$request,
            'issue_date' => $request['date_issued'] ?? now(),
            'noteID' => $this->generateNoteId(),
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['items'] as $value) {
            $debitNoteItems = $record->debitNoteItems()->create([
                'modelable_id' => $value['model_id'],
                'modelable_type' => $value['model'] === 'purchase_invoices' ? DocumentableModelEnums::PURCHASE_INVOICE->value : DocumentableModelEnums::VENDOR_BILLS->value
            ]);
            $debitNoteItems->modelable->lineItems()->update([
                'credit_amount' => $value['debit_amount'],
                'full_credit' => $value['debit_in_full']
            ]);
        }

        return $record;
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Supplier details', 'Date issued', 'Debit note ID', 'referenceID', 'Status'];
        $records = $records->map(function ($record) {
            return [
                $record->vendor->vendor_name ?? null,
                Carbon::parse($record->date_issued)->toFormattedDayDateString(),
                $record->noteID,
                $record->modelable_type === DocumentableModelEnums::PURCHASE_INVOICE->value ? $record->modelable->purchase_invoiceID : $record->modelable->vendor_billID,
                $record->status
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'debit_note_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'debit_note_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function view(int $id)
    {
        $record = DebitNote::select(
            'id',
            'noteID',
            'date_issued',
            'vendor_id',
        )
            ->with([
                'vendor:id,vendor_name,primary_email,referenceID',
                'debitNoteItems:id,modelable_type,modelable_id,status,debit_note_id' => [
                    'modelable' => function (MorphTo $morphTo) {
                        $morphTo->constrain([
                            VendorBill::class => function ($subquery) {
                                $subquery->select('id', 'vendor_billID', 'vendor_bill_due_date', 'is_recurring')
                                    ->withOnly([
                                        'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id,credit_amount,full_credit' => [
                                            'category:id,name'
                                        ],
                                    ]);
                            },
                            PurchaseInvoice::class => function ($subquery) {
                                $subquery->select('id', 'purchase_order_due_date', 'purchase_invoiceID', 'is_recurring')
                                    ->withOnly([
                                        'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id,credit_amount,full_credit' => [
                                            'category:id,name'
                                        ],
                                    ]);
                            },
                        ]);
                    },
                ]
            ])->find($id);

        if (!$record) {
            throw new BadRequestException("Debit note not found!", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function update($request)
    {
        $record = DebitNote::find($request['id']);
        if (!$record) {
            throw new BadRequestException("Debit note not found!", Response::HTTP_NOT_FOUND);
        }
        $record->update([
            ...$request,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['items'] as $value) {
            $documentType = $value['model'] === 'purchase_invoices' ? DocumentableModelEnums::PURCHASE_INVOICE->value : DocumentableModelEnums::VENDOR_BILLS->value;

            $debitNoteItem = $record->debitNoteItems()
                ->where('modelable_id', $value['model_id'])
                ->where('modelable_type', $documentType)
                ->first();

            if ($debitNoteItem) {
                $debitNoteItem->update([
                    'status' => $value['status']
                ]);
            } else {
                $debitNoteItem = $record->debitNoteItems()->create([
                    'modelable_id' => $value['model_id'],
                    'modelable_type' => $documentType
                ]);
            }

            $debitNoteItem->modelable->lineItems()->update([
                'credit_amount' => $value['debit_amount'],
                'full_credit' => $value['debit_in_full']
            ]);
        }

        return $record;
    }

    public function generateNoteId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\DebitNote',
            "modelField" => 'noteID',
            "prefix" => 'n-',
            "idLength" => 6,
        ]);
    }
}
