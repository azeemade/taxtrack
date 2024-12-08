<?php

namespace App\Services\AdminUserServices;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{

    public function overview($request)
    {
        $currentUser = Auth::user();
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->where('created_by', $currentUser->id)
            ->with('roles:id,roleID,name', 'author:id,name')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
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

    public function stats($request)
    {
        $currentUser = Auth::user();
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->where('created_by', $currentUser->id)
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            });

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['ID', 'Name', 'Email Address', 'Status', 'Role Name', 'Created By'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->name,
                $record->email,
                $record->status,
                optional($record->roles->first())->name ?? 'No Role Assigned',
                $record->author->name
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'admin_users_report.xlsx');
    }

    public function create($data)
    {
        $currentUser = auth()->user();

        $password = isset($data['password']) ? $data['password'] : Str::slug($data['name']) . rand(100, 999);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => isset($data['phone_number']) ? $data['phone_number'] : null,
                'password' => Hash::make($password),
                'uei_id' => Str::uuid(),
                'created_by' => $currentUser->id,
            ]);
        }

        if (isset($data['roles'])) {
            $role = Role::where('id', $data['roles'])->first();

            if (!$role) {
                throw new BadRequestException('Role not found', 404);
            }

            $user->assignRole($data['roles']);
        }

        $data = [
            'entity_name' => $data['name'],
            'email' => $data['email'],
            'password' => $password,
        ];

        Mail::to($data['email'])
            ->send(new ClientOnboardingEmail($data));

        return $user;
    }

    public function update($data, $user)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
        ]);

        $user->syncRoles([$data['roles']]);
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

    public function roles()
    {
        $records = Role::query()->where('is_admin', true);

        return $records->get();
    }
}
