<?php

namespace App\Http\Controllers\v1\Auth\ResetPassword;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetLinkRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Responser\JsonResponser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class ResetPasswordController extends Controller
{
    public function sendResetLink(ResetLinkRequest $request)
    {
        try {
            $token = random_int(10000, 99999);

            $user = User::where('email', $request->email)->first();

            DB::table('verification_tokens')->insert([
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => $user->id,
                'token' => $token,
                'expires_at' => now()->addMinutes(5)
            ]);

            $mailData = [
                'email' => $request->email,
                'token' => $token,
            ];
            Notification::route('mail', $request->email)->notify(new ResetPasswordNotification($mailData));
            return JsonResponser::send(false, 'Reset mail sent successfully.', null);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', [], 500, $th);
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::where('email', $request->email)->first();

            $verification = DB::table('verification_tokens')
                ->where('tokenable_type', 'App\\Models\\User')
                ->where('token', $request->token)
                ->latest()
                ->first();

            if (Carbon::parse($verification->expires_at) < now()) {
                $this->updatedToken($request->token);
                return JsonResponser::send(false, 'Token has expired', [], 400);
            }
            
            $this->updatedToken($request->token);

            $user->update([
                'password' => Hash::make($request->password)
            ]);

            DB::commit();
            return JsonResponser::send(false, 'Password updated successfully', [], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal server error', $th->getMessage(), 500, $th);
        }
    }

    protected function updatedToken(string $token)
    {
        DB::table('verification_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('token', $token)
            ->latest()
            ->update([
                'last_used_at' => now()
            ]);
    }
}
