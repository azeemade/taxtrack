<?php

namespace App\Http\Controllers\v1\Admin\UserManagement\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminRoleRequest;
use App\Http\Requests\UpdateAdminRoleRequest;
use App\Models\Role;
use App\Responser\JsonResponser;
use App\Services\AdminRoleServices\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdminRoleRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->roleService->create($request->validated());
            // return $record;

            DB::commit();
            return JsonResponser::send(false, 'Role created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(UpdateAdminRoleRequest $request, Role $role)
    {
        try {
            DB::beginTransaction();

            // Debugging the $role parameter
            if (!$role->exists) {
                return JsonResponser::send(false, 'Role not found or not resolved correctly.');
            }

            dd($role, 'role');
            $record = $this->roleService->update($request->validated(), $role);

            DB::commit();
            return JsonResponser::send(false, 'Role updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
