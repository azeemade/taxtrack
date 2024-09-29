<?php

namespace App\Services\RoleServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function overview($request)
    {
        $records = Role::query()
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
        $records = Role::query();

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
                $record->is_active ? 'Active' : 'Inactive',
                $record->createdBy?->firstname . ' ' . $record->createdBy?->lastname,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'role_report.xlsx');
    }

    public function create(array $data)
    {
        $currentUser = auth()->user();
        $currentUserCompany = $currentUser?->company;
        $roleID = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => 'Spatie\Permission\Models\Role',
            'modelField' => 'roleID',
            'prefix' => 'R-',
            'idLength' => 3
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'guard_name' => 'client',
            'company_id' => $currentUserCompany?->id,
            'created_by' => $currentUser->id,
            'roleID' => $roleID

        ]);
        $role->givePermissionTo($data['permissions']);
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
}
