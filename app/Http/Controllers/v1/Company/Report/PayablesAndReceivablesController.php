<?php

namespace App\Http\Controllers\v1\Company\Report;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\PurchaseInvoice;
use App\Responser\JsonResponser;
use App\Enums\PaymentStatusEnums;
use App\Http\Controllers\Controller;
use App\Enums\FinancialDocumentStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Http\Resources\Company\Reports\AgedPayableDetailsResource;
use App\Http\Resources\Company\Reports\AgedPayableSummaryResource;
use App\Http\Resources\Company\Reports\AgedReceivableDetailsResource;
use App\Http\Resources\Company\Reports\AgedReceivableSummaryResource;
use App\Http\Resources\Company\Reports\PayableInvoiceDetailsResource;
use App\Http\Resources\Company\Reports\PayableInvoiceSummaryResource;
use App\Http\Resources\Company\Reports\ReceivableInvoiceDetailsResource;
use App\Http\Resources\Company\Reports\ReceivableInvoiceSummaryResource;
use Maatwebsite\Excel\Facades\Excel;

class PayablesAndReceivablesController extends Controller
{
    public function agedPayableDetails(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;
            $records = Vendor::whereHas('invoices', function ($query) {
                $query->where('status', FinancialDocumentStatusEnums::ISSUED->value)
                    ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()));
            })
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereHas('invoices', fn($subquery, $dateFilter) => $subquery->whereBetween('created_at', $dateFilter))
                )
                ->when(
                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->whereRelation('invoices', 'purchase_order_due_date', '<', now()),
                            'invoice_date' => $query->whereRelation('invoices', 'invoice_start_date', '<', now()),
                            'created_date' => $query->whereRelation('invoices', 'created_at', '<', now()),
                            'planned_date' => $query->whereRelation('invoices', 'invoice_start_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    }
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy(PurchaseInvoice::select('purchase_invoiceID')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoiceID', 'ASC')->limit(1)),
                            'gross_ascending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'ASC')->limit(1)),
                            'gross_descending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'DESC')->limit(1)),
                            'date_ascending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'ASC')->limit(1)),
                            'date_descending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'DESC')->limit(1)),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )
                ->paginate(50);

            AgedPayableDetailsResource::collection($records);
            if ($request->export) {
                $recordHeadings = ['Customer', 'Date', 'Source', 'Reference', 'Description', 'Quantity', 'Unit price', 'Total', 'Gross total', 'Invoice total', 'Status'];
                $records = $records->flatMap(function ($record) {
                    $rows = [];

                    $rows[] = [$record->company_name];

                    foreach ($record->invoices as $invoice) {
                        $rows[] = [
                            null,
                            $invoice->created_at,
                            'Payable invoice',
                            $invoice->purchase_invoiceID,
                        ];
                        foreach ($invoice->lineItems as $item) {
                            $rows[] = [
                                null,
                                null,
                                null,
                                null,
                                $item->item_details,
                                $item->quantity,
                                $item->price,
                                $item->amount,
                                $invoice->sub_total,
                                $invoice->invoice_value,
                                $invoice->status
                            ];
                        }
                    }

                    return $rows;
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Payable details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function agedPayableSummary(Request $request)
    {
        try {
            $dateFilter = $request->financial_year ? GeneralHelper::dateFilter($request->financial_year) : null;
            $records = Vendor::whereHas('invoices', function ($query) {
                $query->where('status', FinancialDocumentStatusEnums::ISSUED->value)
                    ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()));
            })->when(
                $dateFilter,
                fn($query, $dateFilter) => $query->whereHas('invoices', fn($subquery, $dateFilter) => $subquery->whereBetween('created_at', $dateFilter))
            )
                ->when(
                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->whereRelation('invoices', 'purchase_order_due_date', '<', now()),
                            'invoice_date' => $query->whereRelation('invoices', 'invoice_start_date', '<', now()),
                            'created_date' => $query->whereRelation('invoices', 'created_at', '<', now()),
                            'planned_date' => $query->whereRelation('invoices', 'invoice_start_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    }
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy(PurchaseInvoice::select('purchase_invoiceID')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoiceID', 'ASC')->limit(1)),
                            'gross_ascending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'ASC')->limit(1)),
                            'gross_descending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'DESC')->limit(1)),
                            'date_ascending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'ASC')->limit(1)),
                            'date_descending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'DESC')->limit(1)),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )
                ->paginate(50);

            AgedPayableSummaryResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Contact', '<1M', '1M', '2M', '3M', '>3M', 'Total'];
                $records = $records->map(function ($record) {
                    $vendorTotalAmountDue = $record->invoices->reduce(function ($carry, $item) {
                        return $carry + ($item?->paymentRecords()->latest()->amount_due ?? $item->purchase_invoices_total);
                    }, 0) ?? 0.00;
                    return [
                        $record->vendor_name,
                        $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue >= 1000000 && $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue >= 2000000 && $vendorTotalAmountDue < 3000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue > 3000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue
                    ];
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Payable summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function agedReceivableDetails(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;

            $records = Customer::whereHas('invoices', function ($query) {
                $query->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                    ->where(fn($subquery) => $subquery->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('due_date', '<', now()));
            })
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereHas('invoices', fn($subquery, $dateFilter) => $subquery->whereBetween('created_at', $dateFilter))
                )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->whereRelation('invoices', 'due_date', '<', now()),
                            'invoice_date' => $query->whereRelation('invoices', 'start_date', '<', now()),
                            'created_date' => $query->whereRelation('invoices', 'created_at', '<', now()),
                            'planned_date' => $query->whereRelation('invoices', 'due_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->whereRelation('invoices', 'due_date', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy(Invoice::select('invoiceID')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoiceID', 'ASC')->limit(1)),
                            'gross_ascending' => $query->orderBy(Invoice::select('invoiceID')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoiceID', 'ASC')->limit(1)),
                            'gross_descending' => $query->orderBy(Invoice::select('invoiceID')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoiceID', 'DESC')->limit(1)),
                            'date_ascending' => $query->orderBy(Invoice::select('created_at')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('created_at', 'ASC')->limit(1)),
                            'date_descending' => $query->orderBy(Invoice::select('created_at')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('created_at', 'DESC')->limit(1)),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )
                ->paginate(50);

            AgedReceivableDetailsResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Customer', 'Date', 'Source', 'Reference', 'Description', 'Quantity', 'Unit price', 'Total', 'Gross total', 'Invoice total', 'Status'];
                $records = $records->flatMap(function ($record) {
                    $rows = [];

                    $rows[] = [$record->company_name];

                    foreach ($record->invoices as $invoice) {
                        $rows[] = [
                            null,
                            $invoice->created_at,
                            'Payable invoice',
                            $invoice->invoiceID,
                        ];
                        foreach ($invoice->lineItems as $item) {
                            $rows[] = [
                                null,
                                null,
                                null,
                                null,
                                $item->item_details,
                                $item->quantity,
                                $item->price,
                                $item->amount,
                                $invoice->sub_total,
                                $invoice->invoice_value,
                                $invoice->status
                            ];
                        }
                    }

                    return $rows;
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Receivable details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function agedReceivableSummary(Request $request)
    {
        try {
            $dateFilter = $request->financial_year ? GeneralHelper::dateFilter($request->financial_year) : null;

            $records = Customer::whereHas('invoices', function ($query) {
                $query->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                    ->where(fn($subquery) => $subquery->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('due_date', '<', now()));
            })->when(
                $dateFilter,
                fn($query, $dateFilter) => $query->whereHas('invoices', fn($subquery, $dateFilter) => $subquery->whereBetween('created_at', $dateFilter))
            )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->whereRelation('invoices', 'due_date', '<', now()),
                            'invoice_date' => $query->whereRelation('invoices', 'start_date', '<', now()),
                            'created_date' => $query->whereRelation('invoices', 'created_at', '<', now()),
                            'planned_date' => $query->whereRelation('invoices', 'due_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->whereRelation('invoices', 'due_date', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy(Invoice::select('invoiceID')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoiceID', 'ASC')->limit(1)),
                            'gross_ascending' => $query->orderBy(Invoice::select('invoice_value')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoice_value', 'ASC')->limit(1)),
                            'gross_descending' => $query->orderBy(Invoice::select('invoice_value')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('invoice_value', 'DESC')->limit(1)),
                            'date_ascending' => $query->orderBy(Invoice::select('created_at')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('created_at', 'ASC')->limit(1)),
                            'date_descending' => $query->orderBy(Invoice::select('created_at')->whereColumn('customer_id', 'invoices.id')
                                ->orderBy('created_at', 'DESC')->limit(1)),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )->paginate(50);

            AgedReceivableSummaryResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Contact', '<1M', '1M', '2M', '3M', '>3M', 'Total'];
                $records = $records->map(function ($record) {
                    $vendorTotalAmountDue = $record->invoices->reduce(function ($carry, $item) {
                        return $carry + ($item?->paymentRecords()->latest()->amount_due ?? $item->invoice_value);
                    }, 0) ?? 0.00;
                    return [
                        $record->company_name,
                        $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue >= 1000000 && $vendorTotalAmountDue < 1000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue >= 2000000 && $vendorTotalAmountDue < 3000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue > 3000000 ? $vendorTotalAmountDue : null,
                        $vendorTotalAmountDue
                    ];
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Receivable summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function payableInvoiceDetails(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;

            $records = Vendor::whereRelation('invoices', 'status', FinancialDocumentStatusEnums::ISSUED->value)
                ->where(fn($subquery) => $subquery->whereRelation('invoices', 'payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereRelation('invoices', 'payment_status', null))
                ->where(fn($subquery) => $subquery->whereRelation('invoices', 'purchase_order_due_date', '<', now())->orWhereRelation('invoices', 'invoice_end_date', '<', now()))
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereHas('invoices', fn($subquery, $dateFilter) => $subquery->whereBetween('created_at', $dateFilter))
                )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->whereRelation('invoices', 'purchase_order_due_date', '<', now()),
                            'invoice_date' => $query->whereRelation('invoices', 'invoice_start_date', '<', now()),
                            'created_date' => $query->whereRelation('invoices', 'created_at', '<', now()),
                            'planned_date' => $query->whereRelation('invoices', 'invoice_end_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->whereRelation('invoices', 'purchase_order_due_date', '<', now())->orWhereRelation('invoices', 'invoice_end_date', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy(PurchaseInvoice::select('purchase_invoiceID')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoiceID', 'ASC')->limit(1)),
                            'gross_ascending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'ASC')->limit(1)),
                            'gross_descending' => $query->orderBy(PurchaseInvoice::select('purchase_invoices_total')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('purchase_invoices_total', 'DESC')->limit(1)),
                            'date_ascending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'ASC')->limit(1)),
                            'date_descending' => $query->orderBy(PurchaseInvoice::select('created_at')->whereColumn('vendor_id', 'purchase_invoices.id')
                                ->orderBy('created_at', 'DESC')->limit(1)),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )->paginate(50);

            PayableInvoiceDetailsResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Supplier', 'Date', 'Source', 'Reference', 'Description', 'Quantity', 'Unit price', 'Total', 'Gross total', 'Invoice total', 'Status'];
                $records = $records->flatMap(function ($record) {
                    $rows = [];

                    $rows[] = [$record->vendor_name];

                    foreach ($record->invoices as $invoice) {
                        $rows[] = [
                            null,
                            $invoice->created_at,
                            'Payable invoice',
                            $invoice->purchase_invoiceID,
                        ];
                        foreach ($invoice->lineItems as $item) {
                            $rows[] = [
                                null,
                                null,
                                null,
                                null,
                                $item->item_details,
                                $item->quantity,
                                $item->price,
                                $item->amount,
                                $invoice->sub_total,
                                $invoice->purchase_invoices_total,
                                $invoice->status
                            ];
                        }
                    }

                    return $rows;
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Payable invoice details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function payableInvoiceSummary(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;
            $records = PurchaseInvoice::where('status', FinancialDocumentStatusEnums::ISSUED->value)
                ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()))
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereBetween('created_at', $dateFilter)
                )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->where('purchase_order_due_date', '<', now()),
                            'invoice_date' => $query->where('invoice_start_date', '<', now()),
                            'created_date' => $query->where('created_at', '<', now()),
                            'planned_date' => $query->where('invoice_end_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy('purchase_invoiceID', 'ASC'),
                            'gross_ascending' => $query->orderBy('purchase_invoices_total', 'ASC'),
                            'gross_descending' => $query->orderBy('purchase_invoices_total', 'DESC'),
                            'date_ascending' => $query->orderBy('created_at', 'ASC'),
                            'date_descending' => $query->orderBy('created_at', 'DESC'),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )->paginate(50);

            PayableInvoiceSummaryResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Invoice number', 'Contact', 'Invoice date', 'Source', 'Expected date', 'Gross total', 'Balance', 'Status', 'Sent status'];
                $records = $records->map(function ($record) {
                    return [
                        $record->purchase_invoiceID,
                        $record->vendor->vendor_name,
                        $record->created_at,
                        'Payable invoice',
                        $record->purchase_order_due_date,
                        $record->purchase_invoices_total,
                        $record->paymentRecords()->latest()->first()->amount_due ?? $record->invoice_value,
                        $record->status,
                        $record->share_status
                    ];
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }
            return JsonResponser::send(false, 'Invoice summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Retrieve a details of receivable invoices.
     *
     *
     * @param Request $request The HTTP request object containing optional filters such as 
     * date range (today, this month, last month, last quarter, last financial year, custom_date(2020-04-06|2025-04-06)), 
     * date search(due_date, invoice_date, created_date, planned_date), 
     * export (csv), 
     * and sort by(alphabetically, gross_ascending, gross_descending).
     *
     * @return \App\Responser\JsonResponser A JSON response containing the paginated collection of receivable invoices or an error message.
     */
    public function receivableInvoiceDetails(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;
            $records = Invoice::whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                ->where(fn($query) => $query->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereBetween('created_at', $dateFilter)
                )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->where('due_date', '<', now()),
                            'invoice_date' => $query->where('start_date', '<', now()),
                            'created_date' => $query->where('created_at', '<', now()),
                            'planned_date' => $query->where('due_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->where('created_at', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy('invoiceID', 'ASC'),
                            'gross_ascending' => $query->orderBy('invoice_value', 'ASC'),
                            'gross_descending' => $query->orderBy('invoice_value', 'DESC'),
                            'date_ascending' => $query->orderBy('created_at', 'ASC'),
                            'date_descending' => $query->orderBy('created_at', 'DESC'),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )->paginate(50);

            ReceivableInvoiceDetailsResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Invoice number', 'Contact', 'Invoice date', 'Source', 'Total line quantity', 'Total discounts', 'Total tax', 'Gross total', 'Balance', 'Status', 'Sent status'];
                $records = $records->map(function ($record) {
                    return [
                        $record->invoiceID,
                        $record->customer->company_name,
                        $record->created_at,
                        'Payable invoice',
                        $record->lineItems()->sum('quantity'),
                        $record->lineItems()->sum('discount'),
                        $record->lineItems()->sum('vat'),
                        $record->invoice_value,
                        $record->paymentRecords()->latest()->first()->amount_due ?? $record->invoice_value,
                        $record->status,
                        $record->share_status
                    ];
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }

            return JsonResponser::send(false, 'Receivable invoice retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Retrieve a summary of receivable invoices.
     *
     *
     * @param Request $request The HTTP request object containing optional filters such as 
     * date range (today, this month, last month, last quarter, last financial year, custom_date(2020-04-06|2025-04-06)), 
     * date search(due_date, invoice_date, created_date, planned_date), 
     * export (csv), 
     * and sort by(alphabetically, gross_ascending, gross_descending).
     *
     * @return \App\Responser\JsonResponser A JSON response containing the paginated collection of receivable invoices or an error message.
     */
    public function receivableInvoiceSummary(Request $request)
    {
        try {
            $dateFilter = $request->date_range ? GeneralHelper::dateFilter($request->date_range) : null;
            $records = Invoice::query()

                ->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                ->where(fn($query) => $query->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                ->when(
                    $dateFilter,
                    fn($query, $dateFilter) => $query->whereBetween('created_at', $dateFilter)
                )
                ->when(

                    $request->date_search,
                    function ($query, $dateSearch) {
                        match ($dateSearch) {
                            'due_date' => $query->where('due_date', '<', now()),
                            'invoice_date' => $query->where('start_date', '<', now()),
                            'created_date' => $query->where('created_at', '<', now()),
                            'planned_date' => $query->where('due_date', '<', now()),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->where('created_at', '<', now())
                )
                ->when(
                    $request->sort_by,
                    function ($query, $sortBy) {
                        match ($sortBy) {
                            'alphabetically' => $query->orderBy('invoiceID', 'ASC'),
                            'gross_ascending' => $query->orderBy('invoice_value', 'ASC'),
                            'gross_descending' => $query->orderBy('invoice_value', 'DESC'),
                            'date_ascending' => $query->orderBy('created_at', 'ASC'),
                            'date_descending' => $query->orderBy('created_at', 'DESC'),
                            default => throw new BadRequestException("Invalid date search value", Response::HTTP_BAD_REQUEST)
                        };
                    },
                    fn($query) => $query->latest()
                )->paginate(50);

            ReceivableInvoiceSummaryResource::collection($records);

            if ($request->export) {
                $recordHeadings = ['Invoice number', 'Contact', 'Invoice date', 'Source', 'Expected date', 'Reference', 'Gross total', 'Balance', 'Status', 'Sent status'];
                $records = $records->map(function ($record) {
                    return [
                        $record->invoiceID,
                        $record->customer->company_name,
                        $record->created_at,
                        'Payable invoice',
                        $record->due_date,
                        $record->additional_referenceID,
                        $record->invoice_value,
                        $record->paymentRecords()->latest()->first()->amount_due ?? $record->invoice_value,
                        $record->status,
                        $record->share_status
                    ];
                });
                return Excel::download(new GeneralReportExport($records, $recordHeadings), 'payables_receivables_report.csv', \Maatwebsite\Excel\Excel::CSV);
            }


            return JsonResponser::send(false, 'Receivable invoice summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", $th->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
