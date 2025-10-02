<?php

namespace App\Http\Controllers\v1\Auth;

use App\Enums\GeneralEnums;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\Company\StaffProfileResource;
use App\Models\User;
use App\Responser\JsonResponser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

    /**
     * Login.
     *
     * @param  Illuminate\Http\Request
     *
     * @return \App\Responser\JsonResponser
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            $token = Auth::attempt($credentials);
            if (!$token) {
                return JsonResponser::send(true, 'Invalid email or password', [], Response::HTTP_BAD_REQUEST);
            }

            $user = Auth::user();
            if ($user?->status != GeneralEnums::ACTIVE->value) {
                return JsonResponser::send(true, 'Account is inactive. Contact admin', [], Response::HTTP_BAD_REQUEST);
            }

            if ($user?->hasRole('client') && $user?->company?->status != GeneralEnums::APPROVED->value) {
                return JsonResponser::send(true, 'Company is inactive. Contact admin', [], Response::HTTP_BAD_REQUEST);
            }

            $user->update([
                'current_company_id' => $user?->hasRole('client') && !$user->company ? $user->companies[0]['id'] : $user->current_company_id,
                'last_login' => now()
            ]);

            $data = [
                'user' => $user->current_company_id ? new StaffProfileResource($user) : $user,
                // 'user' => $user,
                'token' => $token,
                'type' => 'bearer',
            ];
            return JsonResponser::send(false, 'User successfully logged in', $data);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    /**
     * Switch companies.
     *
     * @param  int $id //company id
     *
     * @return \App\Responser\JsonResponser
     */
    public function switchCompany($id)
    {
        try {
            $user = Auth::user();
            $company = $user->companies->where('id', $id)->first();
            if (!$company) {
                return JsonResponser::send(true, 'Company does not exist', [], 400);
            }

            $user->update([
                'current_company_id' => $id
            ]);

            $user['company'] = $user->company;
            $user['companies'] = $user->companies;
            $user['permissions'] = User::find($user->id)->getAllPermissions();

            return JsonResponser::send(false, 'Company switched successfully', $user);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Register for admins
     *
     * @param  Illuminate\Http\Request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function signup(SignupRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => Hash::make($request->password),
                'created_by' => Auth::id()
            ]);


            DB::commit();
            return JsonResponser::send(false, 'User created successfully. Please login!', $user);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    /**
     * Logout.
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            Auth::logout();
            return JsonResponser::send(false, 'Successfully logged out', []);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
