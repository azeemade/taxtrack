<?php

namespace App\Services\SuperAdminDashboardServices;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
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
        $records = SubscriptionHistory::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->with('subscriber:id,name')
            ->latest();

        $users = User::query()
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
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

        $revenueChartDataByMonth = []; // Initialize chart data array

        // Loop through each month
        foreach ($months as $key => $month) {
            // Get the start and end dates for the month
            $startOfMonth = Carbon::create(null, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create(null, $month, 1)->endOfMonth();

            // Calculate revenue for the month
            $revenueGenerated = (clone $records)->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('amount');

            // Get month name
            $monthName = date('F', mktime(0, 0, 0, $month, 10));

            // Add data to chart array
            $revenueChartDataByMonth[] = [
                "label" => $monthName,
                "value" => $revenueGenerated, // Revenue for the month
            ];
        }

        $revenueChartDataByPlan = SubscriptionPlan::withSum(
            ['subscriptions as total_revenue' => function ($query) use ($dateFilter) {
                if ($dateFilter) {
                    $query->where('created_at', '>=', $dateFilter);
                }
            }],
            'amount'
        )->get()->map(function ($plan) {
            return [
                'label' => $plan->title,
                'value' => $plan->total_revenue ?? 0, // Default to 0 if null
            ];
        })->toArray();

        $subscriberChartData = []; // Initialize chart data array

        // Loop through each month
        foreach ($months as $key => $month) {
            // Get the start and end dates for the month
            $startOfMonth = Carbon::create(null, $month, 1)->startOfMonth();
            $endOfMonth = Carbon::create(null, $month, 1)->endOfMonth();

            // Calculate revenue for the month
            $subscribers = (clone $records)->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->distinct('subscriber_id')->count('subscriber_id');

            // Get month name
            $monthName = date('F', mktime(0, 0, 0, $month, 10));

            // Add data to chart array
            $subscriberChartData[] = [
                "label" => $monthName,
                "value" => $subscribers, // Revenue for the month
            ];
        }

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
