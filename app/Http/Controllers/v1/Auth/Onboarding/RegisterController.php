<?php

namespace App\Http\Controllers\v1\Auth\Onboarding;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AddCompanyRequest;
use App\Http\Requests\Auth\CreateBasicInformationRequest;
use App\Http\Requests\Auth\CreateOnboardingRoleRequest;
use App\Http\Requests\Auth\InviteUsersRequest;
use App\Models\User;
use App\Notifications\Auth\OnboardingOtpNotification;
use App\Responser\JsonResponser;
use App\Services\CompanyServices\CompanyService;
use App\Services\RoleServices\RoleService;
use App\Services\UserServices\UserService;
use App\Traits\VerificationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    use VerificationTrait;

    protected UserService $userService;
    protected RoleService $roleService;
    protected CompanyService $companyService;

    public function __construct(UserService $userService, CompanyService $companyService, RoleService $roleService)
    {
        $this->userService = $userService;
        $this->roleService = $roleService;
        $this->companyService = $companyService;
    }

    public function basicInformation(CreateBasicInformationRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'country_id' => $request->country_id,
                'currency_id' => $request->currency_id,
                'password' => Hash::make($request->password)
            ]);
            $user->assignRole(['client', 'company admin']);

            $token = $this->generateToken('App\\Models\\User', $user->id, 15);

            $mailData = [
                "name" => $request->name,
                "token" => $token
            ];
            Notification::route('mail', $request->email)->notify(new OnboardingOtpNotification($mailData));

            DB::commit();
            return JsonResponser::send(false, 'User created successfully. An OTP has been sent to your email', $user);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function verifyToken(Request $request, $id)
    {
        try {
            $validate = Validator::make($request->all(), [
                'token' => 'required|digits_between:5,5'
            ]);

            if ($validate->fails()) {
                throw new BadRequestException($validate->errors()->first(), 400);
            }

            DB::beginTransaction();

            $this->verify('App\\Models\\User', $request->token);

            $user = User::find($id);
            $user->update([
                "email_verified_at" => now(),
                "is_verified" => true
            ]);

            DB::commit();
            return JsonResponser::send(false, 'Account verified successfully');
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function resendToken($id)
    {
        try {
            DB::beginTransaction();

            $this->generateToken('App\\Models\\User', $id, 15);

            DB::commit();
            return JsonResponser::send(false, 'Token resent');
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function addCompany(AddCompanyRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            foreach ($request->companies as $company) {
                $company = $this->companyService->create($company, $id);

                $user = User::find($id);

                $companyType = count($request->companies) > 1 ? 'accountant' : 'small-business';
                $user->companies()->attach($company->id, ['company_type' => $companyType, "uei_id" => (string) Str::uuid()]);
                $company->currencies()->attach($user->currency_id);
            }

            DB::commit();
            return JsonResponser::send(false, 'Company created successfully');
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function inviteUsers(InviteUsersRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            foreach ($request->users as $user) {
                $this->userService->create($user, null, $id);
            }

            DB::commit();
            return JsonResponser::send(false, 'Invite has been sent successfully');
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function addRole(CreateOnboardingRoleRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $record = $this->roleService->create(["name" => $request->name], $id, $request->company_id);

            DB::commit();
            return JsonResponser::send(false, 'Role created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }
}
