<?php

namespace App\Services\Invoices;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Helpers\Posting\InvoicePosting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceService
{
    protected SharedActionService $sharedActionServices;
    public function __construct(
        SharedActionService $sharedActionServices
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function all()
    {
        return Invoice::query();
    }

    public function updateOrCreate($request)
    {
        $customer = Customer::find($request['customer_id']);
        $record = Invoice::updateOrCreate(
            [
                "id" => $request["id"] ?? null
            ],
            [
                ...$request,
                'currency_id' => $customer->currency_id,
                'quote_date' => $request['quote_date'] ?? now(),
                'referenceID' => $this->generateRefId(),
                'invoiceID' => $request['invoiceID'] ?? $this->generateInvoiceId(),
                'is_recurring' => $request['save_status'] == 'recur' ? true : false,
                'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
                'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
            ]
        );

        $record->customer->decrement('current_balance', $record->invoice_value);

        if (isset($request['quote_id']) && $request['quote_id']) {
            $quote = Quote::find($request['quote_id']);
            $quote->update([
                'status' => FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value
            ]);

            (new InvoicePosting())->syncInvoiceJournal($record, $record->company_id ?? null, $request['created_by'] ?? null);
        }

        if (isset($request["id"]) && $request["id"]) {
            $record->editLineItems($request['line_items']);
        } else {
            $record->addLineItems($request['line_items']);
        }

        if (isset($request['save_status']) && $request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function view(int $id)
    {
        $record = Invoice::select(
            'id',
            'invoiceID',
            'additional_referenceID',
            'start_date',
            'due_date',
            'terms_and_conditions',
            'customer_note',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'invoice_value',
            'customer_id',
            'currency_id',
        )
            ->with([
                'customer:id,company_name,email',
                'currency:id,name,symbol',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Invoice not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function list($request)
    {
        $records = Invoice::query()
            ->select('id', 'due_date', 'customer_id', 'invoice_value', 'payment_status', 'status', 'invoiceID', 'additional_referenceID', 'category_id')
            ->with([
                'category:id,name',
                'customer:id,company_name',
                'paymentRecords:id,amount_paid,amount_due,recordable_id,recordable_type',
                'lineItems' => [
                    'category:id,name'
                ]
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
            ->when($request->customer_id, function ($query) use ($request) {
                return $query->where('customer_id', $request->customer_id);
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('additional_referenceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('invoiceID', 'LIKE', '%' . $request->q . '%')
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

    public function stats($request)
    {
        $records = Invoice::query()
            ->withSum('paymentRecords as total_amount_paid', 'amount_paid')
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            });

        return [
            'total_invoice_value' => (clone $records)->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])->sum('invoice_value'),
            'total_invoice_amount_due' => (clone $records)->where('status', FinancialDocumentStatusEnums::OVERDUE)->sum('invoice_value'),
            'total_invoice_paid' => (clone $records)->get()->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])->sum('total_amount_paid'),
            'total_invoice_due_today' => (clone $records)->whereDay('due_date', now()->day)->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])->sum('invoice_value'),
        ];
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Customer details', 'Invoice due date', 'Invoice value', 'InvoiceId', 'Amount paid', 'Amount due', 'Payment status', 'Invoice status', 'Line item volume'];
        $records = $records->map(function ($record) {
            return [
                $record->customer->company_name ?? null,
                Carbon::parse($record->due_date)->toFormattedDayDateString(),
                $record->invoice_value ?? 0.00,
                $record->invoiceID,
                $record->total_amount_paid ?? 0.00,
                $record->amount_due ?? 0.00,
                $record->payment_status,
                $record->status,
                count($record->lineItems) ?? 0
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function calculateLineItemTotalUnitPrice(float $unit, int $quantity)
    {
        $lineItemTotalUnitPrice = $unit * $quantity;
        return round($lineItemTotalUnitPrice, 4);
    }

    public function calculateLineItemTotal(float $total_unit_price, float $discount, float $vat)
    {
        $lineItemTotal = ($total_unit_price - (($total_unit_price * $discount) / 100))  * (1 + $vat / 100);
        return round($lineItemTotal, 4);
    }

    public function calculateQuoteSubTotal(array $line_items)
    {
        $quoteSubTotal = 0.00;
        foreach ($line_items as $line_item) {
            $quoteSubTotal += $this->calculateLineItemTotal($line_item['total_unit_price'], $line_item['discount'], $line_item['vat']);
        }
        return round($quoteSubTotal, 4);
    }

    public function calculateQuoteTotal(float $sub_total, float $shipping_charge, float $additional_charge)
    {
        $quoteTotal = $sub_total + $shipping_charge + $additional_charge;
        return round($quoteTotal, 4);
    }

    public function generateInvoiceId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Invoice',
            "modelField" => 'invoiceID',
            "prefix" => 'Inv-',
            "idLength" => 4,
        ]);
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Invoice',
            "modelField" => 'referenceID',
            "prefix" => 'ref-',
            "idLength" => 6,
        ]);
    }
}
