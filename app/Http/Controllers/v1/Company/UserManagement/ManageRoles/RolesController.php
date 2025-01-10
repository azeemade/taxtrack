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
            return JsonResponser::send(true, 'Internal Server Error', null, 500, $th);
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
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500, $th);
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
            return JsonResponser::send(true, 'Internal Server Error', null, 500, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->roleService->update($request->validated(), $id);

            DB::commit();
            return JsonResponser::send(false, 'Role updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function toggleStatus($id)
    {
        try {
            DB::beginTransaction();

            $record = $this->roleService->toggle($id);

            DB::commit();
            return JsonResponser::send(false, 'Role updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $this->roleService->delete($id);

            DB::commit();
            return JsonResponser::send(false, 'Role deleted successfully', null);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * Get all permissions related to specified resource.
     */
    public function permissions($id)
    {
        try {
            $records = $this->roleService->delete($id);
            return JsonResponser::send(false, 'Permission(s) retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }
}
