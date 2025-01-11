<?php

namespace App\Services\RoleServices;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Role;
use Illuminate\Support\Str;

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

    public function export($records, $exportType)
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
        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'role_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'role_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function create(array $data, int $created_by = null, int $company_id = null)
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
                'roleID' => $roleID
            ]);
        }
        if ($created_by && $company_id) {
            $role->companies()->attach($company_id, ["created_by" => $created_by]);
        }

        $permissions = isset($data['permissions']) ? $data['permissions'] : [];
        $role->givePermissionTo($permissions);
        return $role;
    }

    public function update(array $data, $id)
    {
        $immutableRoles = Role::whereIn('slug', ['superadmin', 'admin', 'developer', 'client', 'companyadmin', 'companyuser'])
            ->pluck('id')->toArray();
        if (in_array($id, $immutableRoles)) {
            throw new BadRequestException('Cannot modify this role', 400);
        }

        $role = Role::find($id);
        $role->update([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
        $role->syncPermissions($data['permissions']);
        return $role;
    }

    public function toggle($id)
    {
        $immutableRoles = Role::whereIn('slug', ['superadmin', 'admin', 'developer', 'client', 'companyadmin', 'companyuser'])
            ->pluck('id')->toArray();
        if (in_array($id, $immutableRoles)) {
            throw new BadRequestException('Cannot modify this role', 400);
        }

        $role = Role::find($id);
        $role->update([
            'status' => $role->status == GeneralEnums::ACTIVE->value ? GeneralEnums::INACTIVE->value : GeneralEnums::ACTIVE->value
        ]);
        return $role;
    }

    public function delete($id)
    {
        $immutableRoles = Role::whereIn('slug', ['superadmin', 'admin', 'developer', 'client', 'companyadmin', 'companyuser'])
            ->pluck('id')->toArray();
        if (in_array($id, $immutableRoles)) {
            throw new BadRequestException('Cannot delete this role', 400);
        }
        $role = Role::find($id);
        $role->delete();
    }

    public function permissions($id)
    {
        $role = Role::find($id);
        return $role->permissions;
    }
}
