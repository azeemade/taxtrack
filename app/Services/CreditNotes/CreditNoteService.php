<?php

namespace App\Services\CreditNotes;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class CreditNoteService
{
    protected SharedActionService $sharedActionServices;

    public function __construct(
        SharedActionService $sharedActionServices
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function list($request)
    {
        $records = CreditNote::query()
            ->select('id', 'issue_date', 'invoice_id', 'customer_id', 'referenceID', 'status', 'share_status')
            ->with([
                'invoice:id,invoiceID',
                'customer:id,company_name,email',
            ])
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy(
                        Customer::select('company_name')
                            ->whereColumn('customer_id', 'customers.id')
                            ->orderBy('company_name')
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
                return $query->where('additional_referenceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('referenceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('invoice', 'invoiceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('customer', 'company_name', 'LIKE', '%' . $request->q . '%');
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
        $record = CreditNote::create([
            ...$request,
            'issue_date' => $request['issue_date'] ?? now(),
            'referenceID' => $request['referenceID'] ?? $this->generateRefId(),
            'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['invoices'] as $value) {
            $creditNoteInvoice = $record->creditNoteInvoices()->create([
                ...$value,
                'credit_amount_total' => $value['credit_amount']
            ]);
            $creditNoteInvoice->lineItem()->update([
                'credit_amount' => $value['credit_amount'],
                'full_credit' => $value['credit_in_full']
            ]);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Customer details', 'Date issued', 'InvoiceId', 'referenceID', 'Status', 'Share status'];
        $records = $records->map(function ($record) {
            return [
                $record->customer->company_name ?? null,
                Carbon::parse($record->issue_date)->toFormattedDayDateString(),
                $record->invoice->invoiceID ?? null,
                $record->referenceID ?? null,
                $record->status,
                $record->share_status
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'credit_note_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'credit_note_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function view(int $id)
    {
        $record = CreditNote::select(
            'id',
            'additional_referenceID',
            'issue_date',
            'customer_id',
            'currency_id',
        )
            ->with([
                'customer:id,company_name,email',
                'currency:id,name,symbol',
                'creditNoteInvoices:id,credit_note_id,invoice_id,status' => [
                    'invoice:id,invoiceID,additional_referenceID,due_date' =>
                    [
                        'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id,credit_amount,full_credit' => [
                            'category:id,name'
                        ],
                    ]
                ]
            ])->find($id);

        if (!$record) {
            throw new BadRequestException("Credit note not found!", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function update($request)
    {
        $record = CreditNote::find($request['id']);
        if (!$record) {
            throw new BadRequestException("Credit note not found!", Response::HTTP_NOT_FOUND);
        }
        $record->update([
            ...$request,
            'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['invoices'] as $value) {
            $creditNoteInvoice = $record->creditNoteInvoices()
                ->where('invoice_id', $value['invoice_id'])
                ->where('line_item_id', $value['line_item_id'])
                ->first();

            if ($creditNoteInvoice) {
                $creditNoteInvoice->update([
                    'credit_amount_total' => $value['credit_amount'],
                    'status' => $value['status']
                ]);
            } else {
                $creditNoteInvoice = $record->creditNoteInvoices()->create([
                    ...$value,
                    'credit_amount_total' => $value['credit_amount']
                ]);
            }

            $creditNoteInvoice->lineItem()->update([
                'credit_amount' => $value['credit_amount'],
                'full_credit' => $value['credit_in_full']
            ]);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\CreditNote',
            "modelField" => 'referenceID',
            "prefix" => 'ref-',
            "idLength" => 6,
        ]);
    }
}
