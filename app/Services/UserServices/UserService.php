<?php

namespace App\Services\UserServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public function overview($request)
    {
        $currentUser = Auth::user();
        $currentUserCompany = $currentUser?->company;

        $records = User::query()->where('created_by', $currentUser->id)
            ->orWhere('company_id', $currentUserCompany?->id)
            ->with('roles:id,roleID,name')
            ->withCount('permissions as permissions_count')
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
        $currentUser = Auth::user();
        $currentUserCompany = $currentUser?->company;

        $records = User::query()->where('created_by', $currentUser->id)
            ->orWhere('company_id', $currentUserCompany?->id);

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['ID', 'Name', 'Status', 'Role ID', 'Role', 'No of permissions', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->name,
                $record->status,
                $record->role->roleID,
                $record->role->name,
                $record->permissions_count,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'users_report.xlsx');
    }

    public function create(array $data)
    {
        $currentUser = auth()->user();
        $currentUserCompany = $currentUser?->company;

        $password = $currentUserCompany->name . rand(100, 999);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => Hash::make($password)
        ]);
        $user->assignRole(['client', $data['role']]);

        $data = [
            'entity_name' => $data['name'],
            'company_name' => $currentUserCompany->name,
            'email' => $data['email'],
            'password' => $password,
        ];

        Mail::to($data['email'])
            ->send(new ClientOnboardingEmail($data));

        return $user;
    }

    public function update(array $data, User $user)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
        ]);

        $user->assignRole($data['role']);
        return $user;
    }

    public function toggle(User $user)
    {
        $user->update([
            'status' => $user->status == GeneralEnums::ACTIVE->value ? GeneralEnums::INACTIVE->value : GeneralEnums::ACTIVE->value
        ]);
        return $user;
    }

    public function delete(User $user)
    {
        $user->delete();
    }
}
