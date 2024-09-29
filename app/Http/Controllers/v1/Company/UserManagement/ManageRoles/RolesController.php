<?php

namespace App\Http\Controllers\v1\Company\UserManagement\ManageRoles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Responser\JsonResponser;
use App\Services\RoleServices\RoleService;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class RolesController extends Controller
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

            $stats = $this->roleService->stats();
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
    public function store(StoreRoleRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->roleService->create($request->validated());

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
    public function show(Role $role)
    {
        try {
            $record = $role;
            $record->load('permissions');

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        try {
            DB::beginTransaction();

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
    public function toggleStatus(Role $role)
    {
        try {
            DB::beginTransaction();

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
    public function destroy(Role $role)
    {
        try {
            DB::beginTransaction();

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
    public function permissions(Role $role)
    {
        try {
            $records = $this->roleService->delete($role);
            return JsonResponser::send(false, 'Permission(s) retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
