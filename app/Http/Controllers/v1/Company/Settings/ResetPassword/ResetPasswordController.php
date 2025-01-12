<?php

namespace App\Http\Controllers\v1\Company\Settings\ResetPassword;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Settings\ResetPassword\CurrentPasswordRequest;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{

    /**
     *     
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateCurrentPassword(CurrentPasswordRequest $request)
    {
        try {
            $currentUser = auth()->user();
            $currentPassword = $request->password;
            if (!Hash::check($currentPassword, $currentUser->password)) {
                throw new BadRequestException('Current and new password mismatch', 422);
            }

            $currentUser->sendOtp();

            return JsonResponser::send(false, 'Password confirmation successful. OTP sent!', [], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     *     
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function confirmOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'otp' => 'required|numeric|digits:6',
            ]);
            if ($validator->fails()) {
                throw new BadRequestException($validator->errors()->first(), 422);
            }

            $currentUser = auth()->user();

            if (!$currentUser->verifyOtp($request->otp)) {
                throw new BadRequestException('Invalid OTP', 400);
            }

            return JsonResponser::send(false, 'Otp confirmation successful', [], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     *     
     * @return \Illuminate\Http\Response
     */
    public function resendOtp()
    {
        try {
            $currentUser = auth()->user();

            $currentUser->sendOtp();

            return JsonResponser::send(false, 'Otp resent.', [], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     *     
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createNewPassword(CurrentPasswordRequest $request)
    {
        try {
            $currentUser = auth()->user();
            $currentPassword = $request->password;
            if (Hash::check($currentPassword, $currentUser->password)) {
                throw new BadRequestException('Current and new password cannot be same', 400);
            }
            $currentUser->update([
                'password' => $request->password,
            ]);

            Auth::logout();

            return JsonResponser::send(false, 'Password updated successfully', [], Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
