<?php

namespace App\Services\CompanyServices;

use App\Enums\CompanyStatusEnums;
use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class CompanyService
{
    public function overview($request)
    {
        $records = Company::query()
            ->withCount('staff as staff_count')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
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

    public function stats()
    {
        $records = Company::query();

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('is_active', true)->count(), // Count active records
            'inactive' => (clone $records)->where('is_active', false)->count(), // Count inactive records
            'pending' => (clone $records)->where('status', GeneralEnums::PENDING->value)->count(), // Count active records
            'approved' => (clone $records)->where('status', GeneralEnums::APPROVED->value)->count(), // Count inactive records
            'suspended' => (clone $records)->where('status', GeneralEnums::SUSPENDED->value)->count(), // Count inactive records
            'declined' => (clone $records)->where('status', GeneralEnums::DECLINED->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['id', 'Name', 'Staff count', 'Status', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->companyUUID,
                $record->name,
                $record->staff_count,
                $record->status,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'company_report.xlsx');
    }

    public function create(array $data, int $created_by = null)
    {
        return Company::create([
            'name' => $data['name'],
            'address' => $data['address'],
            'phone_number' => isset($data['phone_number']) ? $data['phone_number'] : null,
            'companyUUID' => GeneralHelper::generateCompanyUUID(),
            'domain' => isset($data['domain']) ? $data['domain'] : null,
            'status' => CompanyStatusEnums::PENDING->value,
            'created_by' => auth()->user()?->id ?: $created_by
        ]);
    }

    public function attachUser($request)
    {
        $company = Company::find($request['company_id']);

        $password = isset($request['password']) ? $request['password'] : Str::slug($company->name) . rand(100, 999);
        $user = User::create([
            'name' => $request['name'],
            'email' => $request['email'],
            'phone_number' => $request['phone_number'],
            'password' => Hash::make($password),
            'created_by' => Auth::id(),
            'company_id' => $request['company_id'],
            'contact_person' => $request['contact_person']
        ]);
        $user->assignRole('client');

        $data = [
            'entity_name' => $request['name'],
            'email' => $request['email'],
            'password' => $password,
        ];

        Mail::to($request['email'])
            ->send(new ClientOnboardingEmail($data));
        return $user;
    }
}
