<?php

namespace App\Services\AdminRoleServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Role;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class RoleService
{
    public function overview($request)
    {

        $dateFilter = $request->date_filter;

        if ($dateFilter === "1day") {
            $carbonDateFilter = Carbon::now()->subdays(1);
        } elseif ($dateFilter === "7days") {
            $carbonDateFilter = Carbon::now()->subdays(7);
        } elseif ($dateFilter === "30days") {
            $carbonDateFilter = Carbon::now()->subdays(30);
        } elseif ($dateFilter === "3months") {
            $carbonDateFilter = Carbon::now()->subMonths(3);
        } elseif ($dateFilter === "12months") {
            $carbonDateFilter = Carbon::now()->subMonths(12);
        } elseif ($dateFilter === "this_year") {
            $carbonDateFilter = Carbon::now()->startOfYear();
        } else {
            $carbonDateFilter = false;
        }

        $records = Role::query()
            ->where('is_admin', true)
            ->withCount('users as users_count')
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
            ->when($carbonDateFilter, function ($query) use ($carbonDateFilter) {
                return $query->where('created_at', '>=', $carbonDateFilter);
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
        $records = Role::query()->where('is_admin', true);

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['RoleID', 'Name', 'Status', 'No of users', 'No of permissions', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->roleID,
                $record->name,
                $record->status,
                $record->users_count,
                $record->permissions_count,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'admin_role_report.xlsx');
    }

    public function create(array $data)
    {
        $roleID = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => 'Spatie\Permission\Models\Role',
            'modelField' => 'roleID',
            'prefix' => 'R-',
            'idLength' => 3
        ]);

        $role = Role::where('slug', Str::slug($data['name']))->first();
        if (!$role) {
            $role = Role::create([
                'name' => Str::title($data['name']),
                'slug' => Str::slug($data['name']),
                'description' => isset($data['description']) ? $data['description'] : null,
                'guard_name' => 'api',
                'is_admin' => true,
                'roleID' => $roleID
            ]);
        }

        $permissions = isset($data['permissions']) ? $data['permissions'] : [];
        $role->givePermissionTo($permissions);
        return $role;
    }

    public function update(array $data, Role $role)
    {
        $role->update([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
        $role->syncPermissions($data['permissions']);
        return $role;
    }

    public function toggle(Role $role)
    {
        $role->update([
            'status' => $role->status == GeneralEnums::ACTIVE->value ? GeneralEnums::INACTIVE->value : GeneralEnums::ACTIVE->value
        ]);
        return $role;
    }

    public function delete(Role $role)
    {
        $role->delete();
    }

    public function permissions()
    {
        $records = Permission::query()->where('app', 'admin');

        return $records->get();
    }
}
