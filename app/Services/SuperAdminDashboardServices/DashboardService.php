<?php

namespace App\Services\SuperAdminDashboardServices;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    public function stats($request)
    {
        $months = range(1, 12);
        $revenueChartDataByMonth = [];
        $subscriberChartData = [];

        $oneMonthAgo = Carbon::now()->subMonths(1);
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);
        // Extract the year from the start date of $dateFilter
        $year = Carbon::parse($dateFilter[0])->year;

        $records = SubscriptionHistory::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', [$dateFilter[0], $dateFilter[1]]);
            })
            ->with('subscriber:id,name')
            ->latest();

        $users = User::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', [$dateFilter[0], $dateFilter[1]]);
            });

        $revenueGeneratedThisMonth = (clone $records)->whereMonth('created_at', Carbon::now()->month)->sum('amount');
        $revenueGeneratedLastMonth = (clone $records)->whereMonth('created_at', Carbon::now()->subMonth()->month)->sum('amount');

        $change = $revenueGeneratedThisMonth - $revenueGeneratedLastMonth;

        // Calculate the percentage change
        if ($revenueGeneratedLastMonth != 0) {
            $revenueGeneratedPercentage = ($change / $revenueGeneratedLastMonth) * 100;
        } else {
            // Handle the case where pastYearValue is zero to avoid division by zero.
            $revenueGeneratedPercentage = 0;
        }

        // Number of distinct users (subscribers) this month and last month
        $usersThisMonth = (clone $records)->whereMonth('created_at', Carbon::now()->month)->distinct('subscriber_id')->count('subscriber_id');
        $usersLastMonth = (clone $records)->whereMonth('created_at', Carbon::now()->subMonth()->month)->distinct('subscriber_id')->count('subscriber_id');

        // Calculate Average Revenue Per User (ARPU)
        $averageRevenuePerUserThisMonth = $usersThisMonth > 0 ? $revenueGeneratedThisMonth / $usersThisMonth : 0;
        $averageRevenuePerUserLastMonth = $usersLastMonth > 0 ? $revenueGeneratedLastMonth / $usersLastMonth : 0;

        // Calculate the percentage change in ARPU
        if ($averageRevenuePerUserLastMonth != 0) {
            $averageRevenuePerUserPercentageChange = (($averageRevenuePerUserThisMonth - $averageRevenuePerUserLastMonth) / $averageRevenuePerUserLastMonth) * 100;
        } else {
            $averageRevenuePerUserPercentageChange = 0; // Handle division by zero
        }

        // Revenue and Subscriber Charts
        foreach ($months as $key => $month) {
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

            $revenueGenerated = (clone $records)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('amount');
            $monthName = date('F', mktime(0, 0, 0, $month, 10));

            $revenueChartDataByMonth[] = [
                "label" => $monthName,
                "value" => $revenueGenerated,
            ];

            $subscribers = (clone $records)->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->distinct('subscriber_id')->count('subscriber_id');

            $subscriberChartData[] = [
                "label" => $monthName,
                "value" => $subscribers,
            ];
        }

        $revenueChartDataByPlan = SubscriptionHistory::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->whereBetween('created_at', [$dateFilter[0], $dateFilter[1]]);
            })
            ->with('plan:id,title')  // Eager load plan relationship
            ->selectRaw('subscription_plan_id, SUM(amount) as total_revenue')
            ->groupBy('subscription_plan_id')
            ->orderBy('total_revenue', 'desc')  // Order by total revenue
            ->get()
            ->map(function ($record) {
                return [
                    'label' => $record->plan->title ?? 'Unknown Plan',
                    'value' => $record->total_revenue ?? 0,
                ];
            })
            ->toArray();

        return [
            'revenueGenerated' => (clone $records)->sum('amount'), // Count total revenue generated
            'revenueGeneratedPercentage' => $revenueGeneratedPercentage,
            'activeUsers' => (clone $users)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'recenActiveUsers' => (clone $users)->where('status', GeneralEnums::ACTIVE->value)->where('created_at', '>=', $oneMonthAgo)->count(), // Count active records
            'activeSubscription' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(),
            'recentActiveSubscription' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->where('created_at', '>=', $oneMonthAgo)->count(),
            'averageRevenuePerUser' => $averageRevenuePerUserThisMonth,
            'averageRevenuePerUserPercentageChange' => $averageRevenuePerUserPercentageChange,
            'revenueChartDataByMonth' => $revenueChartDataByMonth,
            'revenueChartDataByPlan' => $revenueChartDataByPlan,
            'subscriberChartData' => $subscriberChartData
        ];
    }
}
