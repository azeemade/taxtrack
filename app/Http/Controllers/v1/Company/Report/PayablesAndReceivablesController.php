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
            $records = Vendor::whereHas('invoices', function ($query) {
                $query->where('status', FinancialDocumentStatusEnums::ISSUED->value)
                    ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()));
            })->paginate(25);

            AgedPayableDetailsResource::collection($records);
            return JsonResponser::send(false, 'Payable details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function agedPayableSummary(Request $request)
    {
        try {
            $records = Vendor::whereHas('invoices', function ($query) {
                $query->where('status', FinancialDocumentStatusEnums::ISSUED->value)
                    ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()));
            })->paginate(25);

            AgedPayableSummaryResource::collection($records);
            return JsonResponser::send(false, 'Payable summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function agedReceivableDetails(Request $request)
    {
        try {
            $records = Customer::whereHas('invoices', function ($query) {
                $query->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                    ->where(fn($subquery) => $subquery->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('due_date', '<', now()));
            })->paginate(25);

            AgedReceivableDetailsResource::collection($records);
            return JsonResponser::send(false, 'Receivable details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function agedReceivableSummary(Request $request)
    {
        try {
            $records = Customer::whereHas('invoices', function ($query) {
                $query->whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                    ->where(fn($subquery) => $subquery->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('due_date', '<', now()));
            })->paginate(25);

            AgedReceivableSummaryResource::collection($records);
            return JsonResponser::send(false, 'Receivable summary retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function payableInvoiceDetails(Request $request)
    {
        try {
            $records = Vendor::whereHas('invoices', function ($query) {
                $query->where('status', FinancialDocumentStatusEnums::ISSUED->value)
                    ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                    ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()));
            })->paginate(25);

            PayableInvoiceDetailsResource::collection($records);
            return JsonResponser::send(false, 'Payable invoice details retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    public function payableInvoiceSummary(Request $request)
    {
        try {
            $records = PurchaseInvoice::where('status', FinancialDocumentStatusEnums::ISSUED->value)
                ->where(fn($subquery) => $subquery->where('payment_status', PaymentStatusEnums::PARTIAL_PAYMENT->value)->orWhereNull('payment_status'))
                ->where(fn($subquery) => $subquery->where('purchase_order_due_date', '<', now())->orWhere('invoice_end_date', '<', now()))
                ->paginate(25);

            PayableInvoiceSummaryResource::collection($records);
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
            $records = Invoice::whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                ->where(fn($query) => $query->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                ->where(fn($query) => $query->where('due_date', '<', now()))
                ->paginate(25);

            ReceivableInvoiceDetailsResource::collection($records);
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
            $filter = $request->only('date_range', 'date_search', 'export', 'sort_by');
            $records = Invoice::whereIn('status', [FinancialDocumentStatusEnums::ISSUED->value, FinancialDocumentStatusEnums::OVERDUE->value])
                ->where(fn($query) => $query->whereIn('payment_status', [PaymentStatusEnums::PARTIAL_PAYMENT->value, PaymentStatusEnums::PENDING->value])->orWhereNull('payment_status'))
                ->where(fn($query) => $query->where('due_date', '<', now()))
                ->paginate(25);

            ReceivableInvoiceSummaryResource::collection($records);
            return JsonResponser::send(false, 'Receivable invoice retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
