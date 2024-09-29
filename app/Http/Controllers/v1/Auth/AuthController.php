<?php

namespace App\Http\Controllers\v1\Auth;

use App\Enums\GeneralEnums;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\User;
use App\Responser\JsonResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                return JsonResponser::send(true, 'Unauthorized', [], 401);
            }

            $user = Auth::user();
            if ($user?->status != GeneralEnums::ACTIVE->value) {
                return JsonResponser::send(true, 'Account is inactive. Contact admin', [], 401);
            }

            if ($user->hasRole('client') && $user?->company?->status != GeneralEnums::APPROVED->value) {
                return JsonResponser::send(true, 'Company is inactive. Contact admin', [], 401);
            }

            $user->update([
                'last_login' => now()
            ]);

            $data = [
                'user' => $user,
                'token' => $token,
                'type' => 'bearer',
            ];
            return JsonResponser::send(false, 'User successfully logged in', $data);
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
