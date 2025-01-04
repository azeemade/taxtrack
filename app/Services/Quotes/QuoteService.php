<?php

namespace App\Services\Quotes;

use App\Enums\DocumentableModelEnums;
use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\GeneralEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\Invoices\InvoiceService;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class QuoteService
{
    protected InvoiceService $invoiceService;
    protected SharedActionService $sharedActionServices;

    public function __construct(
        InvoiceService $invoiceService,
        SharedActionService $sharedActionServices
    ) {
        $this->invoiceService = $invoiceService;
        $this->sharedActionServices = $sharedActionServices;
    }

    public function list($request)
    {
        $records = Quote::select(
            'id',
            'quote_date',
            'status',
            'share_status',
            'quote_total',
            'quoteID',
            'customer_id'
        )
            ->with(['customer:id,company_name'])
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy(
                        Customer::select('firstname')
                            ->whereColumn('id', 'quotes.customer_id')
                            ->orderBy('company_name')
                            ->limit(1)
                    );
                } else if ($request->sort_by == "amount_ascending") {
                    return $query->orderBy('quote_total', 'asc');
                } else if ($request->sort_by == "amount_descending") {
                    return $query->orderBy('quote_total', 'desc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('quote_date', 'desc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('quote_date', 'desc');
                }
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('quoteID', 'LIKE', '%' . $request->q . '%')
                    ->whereRelation('customer', 'company_name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                if ($request->status == FinancialDocumentStatusEnums::DRAFT->value) {
                    return $query->where('status', FinancialDocumentStatusEnums::DRAFT->value);
                }
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function view(int $id)
    {
        $record = Quote::select(
            'id',
            'quoteID',
            'additional_referenceID',
            'quote_date',
            'terms_and_conditions',
            'customer_note',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'quote_total',
            'customer_id',
            'currency_id',
        )
            ->with([
                'customer:id,company_name',
                'currency:id,name,symbol',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Quote not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function stats($request)
    {
        $records = Quote::query()
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            });

        return [
            'total' => (clone $records)->sum('quote_total'), // Count total records
            'converted_to_invoice' => (clone $records)->where('status', FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value)->sum('quote_total'), // Count active records
            'draft' => (clone $records)->where('status', FinancialDocumentStatusEnums::DRAFT->value)->sum('quote_total'),
        ];
    }

    public function updateOrCreate($request)
    {
        $record = Quote::updateOrCreate(
            [
                "id" => $request["id"] ?? null
            ],
            [
                ...$request,
                'quote_date' => $request['quote_date'] ?? now(),
                'quoteID' => $this->generateQuoteId(),
                'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
                'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : ($request['save_status'] == FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value ? FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value : GeneralEnums::PENDING->value)
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

        if ($request['save_status'] == FinancialDocumentStatusEnums::CONVERTED_TO_INVOICE->value) {
            $this->invoiceService->create($request);
        }

        return $record;
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Customer name', 'Quote date', 'Quote value', 'Status', 'Share status'];
        $records = $records->map(function ($record) {
            return [
                $record?->customer?->company_name,
                Carbon::parse($record->quote_date)->toFormattedDayDateString(),
                $record->quote_total,
                $record->status,
                $record->share_status
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'quotes_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'quotes_report.csv', \Maatwebsite\Excel\Excel::CSV);
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

    public function generateQuoteId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Quote',
            "modelField" => 'quoteID',
            "prefix" => 'qte-',
            "idLength" => 4,
        ]);
    }
}
