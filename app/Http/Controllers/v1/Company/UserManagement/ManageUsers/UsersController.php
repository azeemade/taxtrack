<?php

namespace App\Http\Controllers\v1\Company\UserManagement\ManageUsers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\UserServices\UserService;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $overview = $this->userService->overview($request);

            $stats = $this->userService->stats();
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->userService->export($overview);
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
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();

            $record = $this->userService->create($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'User created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', $th->getTrace(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        try {
            $record = $user;
            $record->load(['role' => ['permissions']]);

            return JsonResponser::send(false, 'Record(s) found successfully', $record);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            DB::beginTransaction();

            $record = $this->userService->update($request->validated(), $user);

            DB::commit();
            return JsonResponser::send(false, 'User updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Update the status specified resource in storage.
     */
    public function toggleStatus(User $user)
    {
        try {
            DB::beginTransaction();

            $record = $this->userService->toggle($user);

            DB::commit();
            return JsonResponser::send(false, 'User updated successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            DB::beginTransaction();

            $this->userService->delete($user);

            DB::commit();
            return JsonResponser::send(false, 'User deleted successfully', null);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
