<?php

namespace App\Http\Controllers\v1\Company\Dashboard;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\TransactionEntryEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\Invoice;
use App\Responser\JsonResponser;
use App\Services\Bills\BillsService;
use App\Services\Customer\CustomerService;
use App\Services\Invoices\InvoiceService;
use App\Services\PaymentRecords\PaymentRecordService;
use App\Services\Supplier\SupplierService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected CustomerService $customerService;
    protected InvoiceService $invoiceService;
    protected BillsService $billsService;
    protected SupplierService $supplierService;
    protected PaymentRecordService $paymentRecordService;

    public function __construct(
        CustomerService $customerService,
        InvoiceService $invoiceService,
        BillsService $billsService,
        SupplierService $supplierService,
        PaymentRecordService $paymentRecordService
    ) {
        $this->customerService = $customerService;
        $this->invoiceService = $invoiceService;
        $this->billsService = $billsService;
        $this->supplierService = $supplierService;
        $this->paymentRecordService = $paymentRecordService;
    }

    /**
     * Display cash flow chart.
     */
    public function cashFlow(SharedFilterRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $currencySymbol = $currentUser->company->currencies->first()->symbol;
            $year = $request->date_filter ?
                [Carbon::createFromDate($request->date_filter, 1, 1), Carbon::createFromDate($request->date_filter, 12, 31)] :
                [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];

            $chartData = [];
            $months = range(1, 12);
            foreach ($months as $month) {
                $chartData[date('F', mktime(0, 0, 0, $month))] = 0.00;
            }

            $records = [
                "cash_at_year_start" => [
                    "name" => "Cash as at " . $year[0]->format("d/m/Y"),
                    "value" => $currencySymbol . "0.00"
                ],
                "cash_at_year_end" => [
                    "name" => "Cash as at " . $year[1]->format("d/m/Y"),
                    "value" => $currencySymbol . "0.00"
                ],
                "incoming" => [
                    "name" => "Incoming",
                    "value" => $currencySymbol . "0.00"
                ],
                "outgoing" => [
                    "name" => "Outgoing",
                    "value" => $currencySymbol . "0.00"
                ],
                "chart_data" => $chartData
            ];
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display total receivables chart.
     */
    public function totalReceivables(SharedFilterRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $currencySymbol = $currentUser->company->currencies->first()->symbol;
            $year = $request->date_filter ?
                [Carbon::createFromDate($request->date_filter, 1, 1), Carbon::createFromDate($request->date_filter, 12, 31)] :
                [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];

            $invoices = $this->invoiceService->all()
                ->whereBetween('created_at', $year);

            $currentSales = (clone $invoices)
                ->where('status', FinancialDocumentStatusEnums::ISSUED->value);
            $overdueSales = (clone $invoices)
                ->where('status', FinancialDocumentStatusEnums::OVERDUE->value);

            $chartData = [];
            $months = range(1, 12);
            foreach ($months as $month) {
                $chartData[date('F', mktime(0, 0, 0, $month))] = [
                    "current_sales" => (clone $currentSales)->whereMonth('created_at', $month)->sum('invoice_value'),
                    "overdue_sales" => (clone $overdueSales)->whereMonth('created_at', $month)->sum('invoice_value')
                ];
            }

            $customers = $this->customerService->all()
                ->select('id', 'company_name')
                ->with([
                    'invoices' =>
                    fn($query) =>
                    $query->select('id', 'invoice_value', 'customer_id', 'due_date', 'created_at')
                        ->whereBetween('created_at', $year)
                ])
                ->limit(5)
                ->get();


            $records = [
                "total_sales_invoice_value" => [
                    "name" => "Total Sales Invoice Value",
                    "value" => $currencySymbol . (clone $invoices)->sum('invoice_value')
                ],
                "current_sales_invoice" => [
                    "name" => "Current Sales Invoice",
                    "value" => $currencySymbol . (clone $currentSales)->sum('invoice_value')
                ],
                "overdue_sales_invoice" => [
                    "name" => "Overdue Sales Invoice",
                    "value" => $currencySymbol . (clone $overdueSales)->sum('invoice_value')
                ],
                "chart_data" => $chartData,
                "outstanding_payment" => $customers->filter(function ($customer) {
                    return $customer->invoices->filter(
                        fn($invoice) => $invoice->due_date >= now() && $invoice->amount_due > 0
                    )->count() > 0;
                })
                    ->map(
                        fn($customer) =>
                        [
                            "name" => $customer->company_name,
                            "amount_due" => $currencySymbol . $currencySymbol . $customer->invoices->sum('amount_due')
                        ]
                    ),
                "overdue_receivables" => $customers->filter(function ($customer) {
                    return $customer->invoices->filter(
                        fn($invoice) => $invoice->due_date < now() && $invoice->amount_due > 0
                    )->count() > 0;
                })
                    ->map(
                        fn($customer) =>
                        [
                            "name" => $customer->company_name,
                            "amount_due" => $currencySymbol . $currencySymbol . $customer->invoices->sum('amount_due')
                        ]
                    ),
            ];
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display total payables chart.
     */
    public function totalPayables(SharedFilterRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $currencySymbol = $currentUser->company->currencies->first()->symbol;
            $year = $request->date_filter ?
                [Carbon::createFromDate($request->date_filter, 1, 1), Carbon::createFromDate($request->date_filter, 12, 31)] :
                [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];

            $bills = $this->billsService->all()
                ->whereBetween('created_at', $year);

            $currentBills = (clone $bills)
                ->where('status', FinancialDocumentStatusEnums::ISSUED->value);
            $overdueBills = (clone $bills)
                ->where('status', FinancialDocumentStatusEnums::OVERDUE->value);

            $chartData = [];
            $months = range(1, 12);
            foreach ($months as $month) {
                $chartData[date('F', mktime(0, 0, 0, $month))] = [
                    "current_bills" => (clone $currentBills)->whereMonth('created_at', $month)->sum('vendor_bill_total'),
                    "overdue_bills" => (clone $overdueBills)->whereMonth('created_at', $month)->sum('vendor_bill_total')
                ];
            }

            $suppliers = $this->supplierService->all()
                ->select('id', 'vendor_name')
                ->with([
                    'vendorBills' =>
                    fn($query) =>
                    $query->select('id', 'vendor_bill_total', 'vendor_id', 'vendor_bill_due_date', 'created_at')
                        ->whereBetween('created_at', $year)
                ])
                ->limit(5)
                ->get();


            $records = [
                "total_bills_value" => [
                    "name" => "Total Bills Value",
                    "value" => $currencySymbol . (clone $bills)->sum('vendor_bill_total')
                ],
                "current_bills" => [
                    "name" => "Current Bills",
                    "value" => $currencySymbol . (clone $currentBills)->sum('vendor_bill_total')
                ],
                "overdue_bills" => [
                    "name" => "Overdue Bills",
                    "value" => $currencySymbol . (clone $overdueBills)->sum('vendor_bill_total')
                ],
                "chart_data" => $chartData,
                "unpaid_suppliers" => $suppliers->filter(function ($supplier) {
                    return $supplier->vendorBills->filter(
                        fn($vendorBill) => $vendorBill->vendor_bill_due_date >= now() && $vendorBill->amount_due > 0
                    )->count() > 0;
                })
                    ->map(
                        fn($supplier) =>
                        [
                            "name" => $supplier->vendor_name,
                            "amount_due" => $currencySymbol . $supplier->vendorBills->sum('amount_due')
                        ]
                    )->values()->toArray(),
                "overdue_payments" => $suppliers->filter(function ($supplier) {
                    return $supplier->vendorBills->filter(
                        fn($vendorBill) => $vendorBill->vendor_bill_due_date < now() && $vendorBill->amount_due > 0
                    )->count() > 0;
                })
                    ->map(
                        fn($supplier) =>
                        [
                            "name" => $supplier->vendor_name,
                            "amount_due" => $currencySymbol . $supplier->vendorBills->sum('amount_due')
                        ]
                    )->values()->toArray(),
            ];
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display income and expense bar chart.
     */
    public function incomeAndExpenseBarChart(SharedFilterRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $currencySymbol = $currentUser->company->currencies->first()->symbol;
            $year = $request->date_filter ?
                [Carbon::createFromDate($request->date_filter, 1, 1), Carbon::createFromDate($request->date_filter, 12, 31)] :
                [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];

            $payments = $this->paymentRecordService->all()
                ->whereBetween('created_at', $year);

            $expenses = (clone $payments)
                ->where('payment_type', TransactionEntryEnums::DEBIT->value);
            $income = (clone $payments)
                ->where('payment_type', TransactionEntryEnums::CREDIT->value);

            $chartData = [];
            $months = range(1, 12);
            foreach ($months as $month) {
                $chartData[date('F', mktime(0, 0, 0, $month))] = [
                    "expenses" => (clone $expenses)->whereMonth('created_at', $month)->sum('amount_paid'),
                    "income" => (clone $income)->whereMonth('created_at', $month)->sum('amount_paid')
                ];
            }


            $records = [
                "total_income" => [
                    "name" => "Total Income",
                    "value" => $currencySymbol . (clone $expenses)->sum('amount_paid')
                ],
                "total_expenses" => [
                    "name" => "Total Expenses",
                    "value" => $currencySymbol . (clone $income)->sum('amount_paid')
                ],
                "chart_data" => $chartData,
            ];
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display income and expense pie chart.
     */
    public function incomeAndExpensePieChart(SharedFilterRequest $request)
    {
        try {
            $currentUser = Auth::user();
            $currencySymbol = $currentUser->company->currencies->first()->symbol;
            $year = $request->date_filter ?
                [Carbon::createFromDate($request->date_filter, 1, 1), Carbon::createFromDate($request->date_filter, 12, 31)] :
                [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];

            $chartData = [];
            $pies = ["Automate Expense", "IT and Internet Expense", "Cost of Goods Sold", "Consultant Expense", "Office Supplies", "Others"];
            foreach ($pies as $pie) {
                $chartData[] = [
                    "name" => $pie,
                    "value" => rand(5, 30)
                ];
            }

            $records = [
                "chart_data" => $chartData,
            ];
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), null, $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
