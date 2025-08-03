<?php

namespace App\Http\Controllers\v1\Company\Sales\Customer;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerAnalyticsController extends Controller
{
    public function paymentDuration(Request $request)
    {
        $request->merge([
            'year' => $request->year ?? date('Y'),
        ]);
        try {
            $records = [];
            $months = range(1, 12);
            foreach ($months as $month) {
                $records[date('F', mktime(0, 0, 0, $month))] = Invoice::whereYear('due_date', $request->year)->whereMonth('due_date', $month)
                    ->get()
                    ->reduce(function ($carry, $invoice) {
                        if ($invoice->paymentRecords->isEmpty()) {
                            return number_format($carry + Carbon::parse($invoice->due_date)->diffInDays(now()), 2);
                        }

                        $lastPayment = $invoice->paymentRecords->sortByDesc('paid_on')->first();
                        return number_format($carry + Carbon::parse($invoice->due_date)->diffInDays(Carbon::parse($lastPayment->paid_on)), 2);
                    }) ?? strval(0);
            }

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function paymentConsistency(Request $request)
    {
        $request->merge([
            'year' => $request->year ?? date('Y'),
        ]);
        try {
            $records = [];
            Customer::whereHas('invoices', function ($query) use ($request) {
                $query->whereYear('due_date', $request->year);
            })->take(20)->get()
                ->each(function (Customer $customer) use (&$records, $request) {
                    $totalInvoiceValue = 0;
                    $totalPaid = 0;
                    $customer->invoices()
                        ->whereYear('due_date', $request->year)
                        ->each(function (Invoice $invoice) use (&$totalInvoiceValue, &$totalPaid) {
                            $totalInvoiceValue += $invoice->invoice_value;
                            $totalPaid += $invoice->paymentRecords->sum('amount_paid');
                        });
                    $score = $totalInvoiceValue > 0 ? round(($totalPaid / $totalInvoiceValue) * 100, 2) : 0;
                    $records[] = [
                        'customer' => $customer->only('company_name', 'customerID'),
                        'score' => number_format($score / 10, 2)
                    ];
                });

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function outstandingBalance(Request $request)
    {
        $request->merge([
            'year' => $request->year ?? date('Y'),
        ]);
        try {
            $invoices = Invoice::query()->whereYear('due_date', $request->year);
            $records = [
                'current_balance' => (clone $invoices)->where('payment_status', 'pending')->where('due_date', '<', now())->sum('invoice_value'),
                'outstanding_balance' => (clone $invoices)->where('payment_status', 'pending')->where('due_date', '>=', now())->sum('invoice_value'),
                'payment_overdue_days' => []
            ];
            $days = range(5, 40, 5);
            foreach ($days as $key => $day) {
                $records['payment_overdue_days'][$key] = [
                    'days' => $day,
                    'count' => 0,
                ];
                (clone $invoices)->where('due_date', '<', now())
                    ->get()
                    ->each(function (Invoice $invoice) use (&$records, $day, $days, $key) {
                        $firstInvoicePayment = $invoice->paymentRecords->sortBy('paid_on')->first();
                        $diff = Carbon::parse($invoice->due_date)->diffInDays(Carbon::parse($firstInvoicePayment->paid_on ?? now()));
                        $index = array_search($day, $days);
                        $isFirst = $index === 0;
                        $isLast = $index === count($days) - 1;
                        if (
                            $isFirst && $diff > 0 && $diff <= $day ||
                            $isLast && $diff > $day ||
                            !$isFirst && !$isLast && $diff > $days[$index - 1] && $diff <= $day
                        ) {
                            $records['payment_overdue_days'][$key] = [
                                'days' => $day,
                                'count' => ($records['payment_overdue_days'][$key]['count'] ?? 0) + 1,
                            ];
                        }
                    });
            }

            return JsonResponser::send(false, 'Record(s) retrieved successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
