<?php

namespace App\Services\ManageSubscriptionServices;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Module;
use App\Models\Subscriber;
use App\Models\SubscriptionFunctionality;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use App\Models\SubscriptionRefund;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class SubscriptionService
{

    public function overview($request)
    {
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = SubscriptionHistory::query()
            ->where('subscription_plan_id', $request->plan_id)
            ->with('subscriber:id,name', 'plan:id,title')
            ->when($request->q, function ($query) use ($request) {
                $query->whereRelation('subscriber', 'name', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('plan', 'title', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
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

        $months = range(1, 12);
        $revenueChartData = [];
        $subscriberChartData = [];

        $records = SubscriptionHistory::query()
            ->where('subscription_plan_id', $request->plan_id)
            ->with('subscriber:id,name')
            ->latest();

        $lastUpdatedRecord = $records->first(); // Get the first record
        $lastUpdated = $lastUpdatedRecord
            ? round(Carbon::parse($lastUpdatedRecord->updated_at)->diffInDays(now())) . ' days ago, By ' . ($lastUpdatedRecord->subscriber->name ?? 'Unknown')
            : null; // Handle cases where no records exist

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

        $subscriberThisMonth = (clone $records)->whereMonth('created_at', Carbon::now()->month)->distinct('subscriber_id')->count('subscriber_id');
        $subscriberLastMonth = (clone $records)->whereMonth('created_at', Carbon::now()->subMonth()->month)->distinct('subscriber_id')->count('subscriber_id');

        $change = $subscriberThisMonth - $subscriberLastMonth;

        // Calculate the percentage change
        if ($subscriberLastMonth != 0) {
            $subscriberPercentage = ($change / $subscriberLastMonth) * 100;
        } else {
            // Handle the case where pastYearValue is zero to avoid division by zero.
            $subscriberPercentage = 0;
        }

        $revenueChartData = []; // Initialize chart data array

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
            $revenueChartData[] = [
                "label" => $monthName,
                "value" => $revenueGenerated, // Revenue for the month
            ];
        }

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
            'subscriberCount' => (clone $records)->distinct('subscriber_id')->count('subscriber_id'), // Count total subscribers
            'subscriberPercentage' => $subscriberPercentage,
            'lastUpdated' => $lastUpdated,
            'revenueChartData' => $revenueChartData,
            'subscriberChartData' => $subscriberChartData
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
                $record->amount,
                $record->created_at,
                $record->end_date
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'subscription_report.xlsx');
    }

    public function create($data)
    {
        $currentUser = auth()->user();

        $plan = SubscriptionPlan::create([
            'title' => $data['title'],
            'monthly_fee' => $data['monthly_fee'],
            'yearly_fee' => $data['yearly_fee'],
            'short_description' => $data['short_description'],
            'primary_cta_text' => $data['primary_cta_text'],
            'primary_link' => $data['primary_link'],
            'secondary_cta' => $data['secondary_cta'],
            'secondary_link' => $data['secondary_link'],
            'created_by' => $currentUser->id
        ]);

        if (isset($data['features'])) {
            foreach ($data['features'] as $title) {
                SubscriptionPlanFeature::create([
                    'title' => $title,
                    'subscription_plan_id' => $plan->id
                ]);
            }
        }

        if (isset($data['modules'])) {
            foreach ($data['modules'] as $module) {
                SubscriptionFunctionality::create([
                    'subscription_plan_id' => $plan->id,
                    'module_id' => $module['module_id'],
                    'module_functionality_id' => $module['module_functionality_id']
                ]);
            }
        }

        return $plan;
    }

    public function update($data, $plan)
    {
        $plan->update([
            'title' => $data['title'],
            'monthly_fee' => $data['monthly_fee'],
            'yearly_fee' => $data['yearly_fee'],
            'short_description' => $data['short_description'],
            'primary_cta_text' => $data['primary_cta_text'],
            'primary_link' => $data['primary_link'],
            'secondary_cta' => $data['secondary_cta'],
            'secondary_link' => $data['secondary_link'],
        ]);

        if (isset($data['features'])) {
            foreach ($data['features'] as $title) {
                // Remove features that are no longer in the update
                SubscriptionPlanFeature::where('subscription_plan_id', $plan->id)->where('title', $title)->delete();
                SubscriptionPlanFeature::updateOrCreate(
                    [
                        'title' => $title,
                        'subscription_plan_id' => $plan->id,
                    ],
                    [
                        'title' => $title,
                    ]
                );
            }
        }

        if (isset($data['modules'])) {
            foreach ($data['modules'] as $module) {
                // Remove module functionalities that are no longer in the update
                SubscriptionFunctionality::where('subscription_plan_id', $plan->id)
                    ->where('module_id', $module['module_id'])
                    ->where('module_functionality_id', $module['module_functionality_id'])->delete();

                SubscriptionFunctionality::updateOrCreate(
                    [
                        'subscription_plan_id' => $plan->id,
                        'module_id' => $module['module_id'],
                        'module_functionality_id' => $module['module_functionality_id'],
                    ],
                    [
                        'module_id' => $module['module_id'],
                        'module_functionality_id' => $module['module_functionality_id'],
                    ]
                );
            }
        }

        return $plan;
    }

    public function createHistory($data)
    {
        $receiptNo = $this->generateUniqueId();
        $customerReferNo = mt_rand(1000, 9999);
        $subscriber = Subscriber::where('id', $data['subscriber_id'])->first();
        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'name' => 'Test',
                'current_subscription_plan_id' => 2,
                'company_id' => 11,
                'user_id' => 22,
            ]);
        }
        $history = SubscriptionHistory::create([
            'receipt_no' => $receiptNo,
            'customer_refer_no' => $customerReferNo,
            'billed_per' => $data['billed_per'],
            'amount' => $data['amount'],
            'subscribed_at' => now(),
            'end_date' => $data['end_date'],
            'status' => 'active',
            'payment_type' => $data['payment_type'],
            'paid_via' => $data['paid_via'],
            'subscriber_id' => $subscriber->id,
            'subscription_plan_id' => $data['subscription_plan_id']
        ]);

        return $history;
    }

    public function toggle(SubscriptionPlan $plan)
    {
        $plan->update([
            'status' => $plan->status == GeneralEnums::ACTIVE->value ? GeneralEnums::INACTIVE->value : GeneralEnums::ACTIVE->value
        ]);
        return $plan;
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

    public function delete(SubscriptionPlan $plan)
    {
        $plan->delete();
    }

    public function module()
    {
        $records = Module::with('moduleFunctionality')->orderBy('id', 'DESC');

        if (!$records) {
            throw new BadRequestException('Role not found', 404);
        }

        return $records->get();
    }

    protected function generateUniqueId()
    {
        $receiptNo = 'P000' . mt_rand(
            10000,
            99999
        );
        $record = SubscriptionHistory::where('receipt_no', $receiptNo)->first();

        if ($record) {
            return $this->generateUniqueId();
        }

        return $receiptNo;
    }
}
