<?php

namespace App\Http\Controllers\v1\Admin\UserManagement\Users;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\AdminUserServices\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
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
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();

            // $record = $this->userService->create($request->validated());

            $currentUser = auth()->user();
            $data = $request->validated();
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
                dd($user);
            }

            DB::commit();
            return JsonResponser::send(false, 'User created successfully', $user);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
