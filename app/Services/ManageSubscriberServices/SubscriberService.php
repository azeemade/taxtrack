<?php

namespace App\Services\ManageSubscriberServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Subscriber;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRefund;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class SubscriberService
{
    public function overview($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = Subscriber::query()
            ->with(['subscriptionPlan:id,title', 'currentSubscriptionHistory'])
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('subscriptionPlan', 'title', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->orWhereRelation('currentSubscriptionHistory', 'status', $request->status);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderBy('name', 'ASC');
            });

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit);
        }

        return $records->get();
    }

    public function stats($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);
        $twoMonthsAgo = Carbon::now()->subMonths(2);

        $records = Subscriber::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            });

        $plans = SubscriptionPlan::query()
            ->select('title') // Select only the title of the SubscriptionPlan
            ->withCount(['subscriber as subscriber_count' => function ($query) use ($dateFilter) {
                // Filter subscribers by the dateFilter
                if ($dateFilter) {
                    $query->where('created_at', '>=', $dateFilter);
                }
            }]);

        return [
            'totalSubscriberCount' => (clone $records)->count(), // Count total records
            'recentSubscriberCount' => (clone $records)->where('created_at', '>=', $twoMonthsAgo)->count(), // Count total records
            'plans' => (clone $plans)->get(), // Count active records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['ID', 'Subscriber Name', 'Plan Details', 'Status', 'Billing Type', 'Transaction Value', 'Start Date', 'End Date'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->name,
                $record->subscriptionPlan->title,
                $record->currentSubscriptionHistory->status ?? null,
                $record->currentSubscriptionHistory->billed_per ?? null,
                $record->currentSubscriptionHistory->amount_paid ?? null,
                !empty($record->currentSubscriptionHistory->subscribed_at) ? Carbon::parse($record->currentSubscriptionHistory->subscribed_at) : null,
                !empty($record->currentSubscriptionHistory->end_date) ? Carbon::parse($record->currentSubscriptionHistory->end_date) : null
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'subscribers_report.xlsx');
    }

    public function toggleStatus(SubscriptionRefund $refund, string $status)
    {
        $currentUser = auth()->user();
        $refund->update([
            'status' => $status,
            'approved_on' => now(),
            'approved_by' => $currentUser->id
        ]);
        return $refund;
    }
}