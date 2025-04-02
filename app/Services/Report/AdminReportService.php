<?php

namespace App\Services\Report;

use App\Exports\GeneralReportExport;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class AdminReportService
{
    public function companies($request)
    {
        $records = Company::query()
            ->withCount('staff')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->sort_by == 'alphabetically', function ($query) {
                $query->orderBy('name', 'ASC');
            })->latest();

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit);
        }
        return $records->get();
    }

    public function exportCompanies($records, $export)
    {
        $recordHeadings = ['id', 'Name', 'Email', 'Contact person', 'Staff count', 'Status', 'Current plan', 'Subscription status', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->companyUUID,
                $record->name,
                $record->email,
                $record->companyAdmin->name,
                $record->staff_count,
                $record->status,
                $record->subscriber->subscriptionPlan->name ?? null,
                $record->subscriber->currentSubscriptionHistory->status ?? null,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });

        return match ($export) {
            "pdf" => Excel::download(new GeneralReportExport($records, $recordHeadings), 'company_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF),
            "csv" => Excel::download(new GeneralReportExport($records, $recordHeadings), 'company_report.csv', \Maatwebsite\Excel\Excel::CSV),
            default => Excel::download(new GeneralReportExport($records, $recordHeadings), 'company_report.xlsx'),
        };
    }
}
