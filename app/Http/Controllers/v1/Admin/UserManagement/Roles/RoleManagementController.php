<?php

namespace App\Http\Controllers\v1\Admin\UserManagement\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
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
    public function index(SharedFilterRequest $request)
    {
        try {
            $overview = $this->roleService->overview($request);

            $stats = $this->roleService->stats($request);
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->roleService->export($overview);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, 500);
        }
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
    public function show($id)
    {
        try {
            $record = Role::where('id', $id)->first();

            if (!$record) {
                return JsonResponser::send(false, 'Role not found.');
            }

            $record->load('permissions');

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(UpdateAdminRoleRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $role = Role::where('id', $id)->first();

            if (!$role) {
                return JsonResponser::send(false, 'Role not found.');
            }

            $record = $this->roleService->update($request->validated(), $role);

            DB::commit();
            return JsonResponser::send(false, 'Role updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function toggleStatus($id)
    {
        try {
            DB::beginTransaction();

            $role = Role::where('id', $id)->first();

            if (!$role) {
                return JsonResponser::send(false, 'Role not found.');
            }

            $record = $this->roleService->toggle($role);

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
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $role = Role::where('id', $id)->first();

            if (!$role) {
                return JsonResponser::send(false, 'Role not found.');
            }

            $this->roleService->delete($role);

            DB::commit();
            return JsonResponser::send(false, 'Role deleted successfully', null);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }

    /**
     * Get all permissions related to specified resource.
     */
    public function permissions()
    {
        try {
            $records = $this->roleService->permissions();
            return JsonResponser::send(false, 'Permission(s) retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
