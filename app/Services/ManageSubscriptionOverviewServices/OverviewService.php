<?php

namespace App\Services\ManageSubscriptionOverviewServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRefund;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class OverviewService
{

    public function overview($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = SubscriptionHistory::query()
            ->with(['subscriber:id,name', 'plan:id,title'])
            ->when($request->q, function ($query) use ($request) {
                $query->whereRelation('subscriber', 'name', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('plan', 'title', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when(is_array($dateFilter) && count($dateFilter) === 2, function ($query) use ($dateFilter) {
                $query->whereBetween('created_at', [$dateFilter[0], $dateFilter[1]]);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderByRelation('plan', 'title', 'ASC');
            })->latest();

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit);
        }
        return $records->get();
    }

    public function stats($request)
    {
        $twoMonthsAgo = Carbon::now()->subMonths(2);
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);
        $records = SubscriptionHistory::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->with('subscriber:id,name')
            ->latest();

        $plans = SubscriptionPlan::query()
            ->select('id', 'title', 'monthly_fee', 'yearly_fee') // Select only the title of the SubscriptionPlan
            ->withCount(['subscriber as subscriber_count' => function ($query) use ($dateFilter) {
                // Filter subscribers by the dateFilter
                if ($dateFilter) {
                    $query->where('created_at', '>=', $dateFilter);
                }
            }])->withSum('subscriptions', 'amount_paid');

        $revenueGeneratedThisMonth = (clone $records)->whereMonth('created_at', Carbon::now()->month)->sum('amount_paid');
        $revenueGeneratedLastMonth = (clone $records)->whereMonth('created_at', Carbon::now()->subMonth()->month)->sum('amount_paid');

        $change = $revenueGeneratedThisMonth - $revenueGeneratedLastMonth;

        // Calculate the percentage change
        if ($revenueGeneratedLastMonth != 0) {
            $revenueGeneratedPercentage = ($change / $revenueGeneratedLastMonth) * 100;
        } else {
            // Handle the case where pastYearValue is zero to avoid division by zero.
            $revenueGeneratedPercentage = 0;
        }

        return [
            'revenueGenerated' => (clone $records)->sum('amount_paid'), // Count total revenue generated
            'revenueGeneratedPercentage' => $revenueGeneratedPercentage,
            'activeSubscriber' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count('subscriber_id'),
            'inactivesSubscriber' => (clone $records)->where('status', GeneralEnums::EXPIRED->value)->count('subscriber_id'),
            'subscriberCount' => (clone $records)->distinct('subscriber_id')->count('subscriber_id'), // Count total subscribers
            'recentSubscriberCount' => (clone $records)->distinct('subscriber_id')->where('created_at', '>=', $twoMonthsAgo)->count('subscriber_id'), // Count total records
            'plans' => (clone $plans)->get(), // Count active records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['ID', 'Subscriber Name', 'Plan Details', 'Billing Type', 'Transaction Value', 'Start Date', 'End Date'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->subscriber->name,
                $record->plan->title,
                $record->status,
                $record->billed_per,
                $record->amount_paid,
                Carbon::parse($record->created_at),
                Carbon::parse($record->endDate)
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'subscription_report.xlsx');
    }

    public function approve(SubscriptionRefund $refund)
    {
        $currentUser = auth()->user();
        $refund->update([
            'status' => GeneralEnums::APPROVED->value,
            'approved_on' => now(),
            'approved_by' => $currentUser->id
        ]);
        return $refund;
    }
}
