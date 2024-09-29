<?php

namespace App\Http\Controllers\v1\Company\UserManagement\ManageRoles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UserManagement\Roles\CreateRoleRequest;
use App\Responser\JsonResponser;
use App\Services\RoleServices\RoleService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolesManagementController extends Controller
{

    public function overview(Request $request, RoleService $roleService)
    {
        try {
            $records = $roleService->create($request->validated());

            return JsonResponser::send(false, 'Role created successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function view(Role $id, RoleService $roleService)
    {
        try {
            $record = $roleService->find($id);

            return JsonResponser::send(false, 'Role created successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function store(CreateRoleRequest $request, RoleService $roleService)
    {
        try {
            $record = $roleService->create($request->validated());

            return JsonResponser::send(false, 'Role created successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    public function update(CreateRoleRequest $request, Role $id, RoleService $roleService)
    {
        try {
            $record = $roleService->update($request->validated(), $id);

            return JsonResponser::send(false, 'Role updated successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }
}
