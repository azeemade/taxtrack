<?php

namespace App\Http\Controllers\v1\Auth\Onboarding;

use App\Enums\CustomerTypeEnums;
use App\Exceptions\BadRequestException;
use App\Helpers\Posting\CoaProvisionerFromConfig;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AddCompanyRequest;
use App\Http\Requests\Auth\CompanyUserRequest;
use App\Http\Requests\Auth\CreateBasicInformationRequest;
use App\Http\Requests\Auth\CreateOnboardingRoleRequest;
use App\Http\Requests\Auth\InviteUsersRequest;
use App\Http\Resources\CompanyHouseCompanyResource;
use App\Jobs\Company\ProcessCompanyOnboarding;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\Auth\OnboardingOtpNotification;
use App\Responser\JsonResponser;
use App\Services\Company\CompanyService;
use App\Services\ManageSubscriptionServices\CompanySubscriptionService;
use App\Services\RoleServices\RoleService;
use App\Services\ThirdPartyApi\CompanyHouseApi;
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
    protected CompanySubscriptionService $companySubscriptionService;

    public function __construct(
        UserService $userService,
        CompanyService $companyService,
        RoleService $roleService,
        CompanySubscriptionService $companySubscriptionService
    ) {
        $this->userService = $userService;
        $this->roleService = $roleService;
        $this->companyService = $companyService;
        $this->companySubscriptionService = $companySubscriptionService;
    }

    public function userCheck(Request $request)
    {
        try {
            DB::beginTransaction();

            $validate = Validator::make($request->all(), [
                'email' => 'required|string|email|max:250'
            ]);

            if ($validate->fails()) {
                throw new BadRequestException($validate->errors()->first(), 400);
            }

            $record = User::where(
                'email',
                $request->email
            )->first();

            if ($record?->onboarding_completed) {
                throw new BadRequestException('Account exist, please login', 400);
            }

            $onboarding = false;
            if ($record?->hasRole('company admin')) {
                $token = $this->generateToken('App\Models\User', $record->id ?? 0, 15);

                $mailData = [
                    "name" => $request->name,
                    "token" => $token
                ];
                Notification::route('mail', $request->email)->notify(new OnboardingOtpNotification($mailData));
                $onboarding = true;
            }

            $response = [
                'started_onboarding' => $onboarding,
                'email' => $request->email,
                'onboarded_companies' => $record?->hasRole('company admin') && count($record?->companies) > 0 ? $record?->companies : [],
                'user_id' => $record?->id ?? null,
            ];

            DB::commit();
            return JsonResponser::send(
                false,
                $onboarding ? 'An OTP has been sent to your email' : 'Complete your basic information',
                $response
            );
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function basicInformation(CreateBasicInformationRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::updateOrCreate(
                [
                    'email' => $request->email
                ],
                [
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone_number' => $request->phone_number,
                    'country_id' => $request->country_id,
                    'currency_id' => $request->currency_id,
                    'company_type' => $request->company_type,
                    'password' => Hash::make($request->password)
                ]
            );
            $user->assignRole(['client', 'company admin']);

            $token = $this->generateToken('App\Models\User', $user->id, 15);

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

    public function verifyToken(Request $request)
    {
        try {
            DB::beginTransaction();

            $validate = Validator::make($request->all(), [
                'token' => 'required|digits_between:5,5',
                'email' => 'required|string|email|max:250|exists:users,email'
            ]);

            if ($validate->fails()) {
                throw new BadRequestException($validate->errors()->first(), 400);
            }

            $record = User::where(
                'email',
                $request->email
            )->first();

            $this->verify('App\Models\User', $request->token, $record->id);

            $record->update([
                "email_verified_at" => now(),
                "is_verified" => true
            ]);

            DB::commit();
            return JsonResponser::send(false, 'Account verified successfully', $record->only('id', 'name', 'email', 'uei_id'));
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function resendToken(Request $request)
    {
        try {
            DB::beginTransaction();

            $validate = Validator::make($request->all(), [
                'email' => 'required|string|email|max:250|exists:users,email'
            ]);

            if ($validate->fails()) {
                throw new BadRequestException($validate->errors()->first(), 400);
            }

            $record = User::where(
                'email',
                $request->email
            )->first();

            $token = $this->generateToken('App\Models\User', $record->id, 15);

            $mailData = [
                "name" => $request->name,
                "token" => $token
            ];
            Notification::route('mail', $request->email)->notify(new OnboardingOtpNotification($mailData));

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
            // DB::beginTransaction();

            $user = User::find($id);
            if (!$user) {
                throw new BadRequestException("User doesn't exist", 404);
            }
            $request->validated();
            // $user->update([
            //     'can_login' => true,
            //     'is_active' => true
            // ]);

            // if (
            //     (isset($request->companies) &&
            //         count($request->companies) > 1) &&
            //     $user->company_type != CustomerTypeEnums::ACCOUNTANT->value
            // ) {
            //     throw new BadRequestException("Multiple companies not allowed for small business", 400);
            // }

            // foreach ($request->companies as $key => $company) {
            //     $company = $this->companyService->create($company, $id);
            //     if ($key === array_key_first($request->companies)) {
            //         $user->update([
            //             "current_company_id" => $company->id
            //         ]);
            //     }

            //     $user->companies()->attach($company->id, ['company_type' => $user->company_type, "uei_id" => (string) Str::uuid()]);
            //     $company->currencies()->attach($user->currency_id);

            //     if (isset($company['subscription_plan_id']) && $company['subscription_plan_id']) {
            //         $planId = $company['subscription_plan_id'];
            //         $free = false;
            //         $duration = $company['duration'];
            //     } else {
            //         $freePlan = SubscriptionPlan::where('is_free', true)->first();
            //         $planId = $freePlan->id;
            //         $free = true;
            //         $duration = $freePlan->duration > 30 ? 'yearly' : 'monthly';
            //     }
            //     $this->companySubscriptionService->subscribeToPlan([
            //         'user_id' => $id,
            //         'company_id' => $company->id,
            //         'subscription_plan_id' => $planId,
            //         'is_free' => $free,
            //         'duration' => $duration,
            //         'additional_users_count' => $company['additional_users_count'] ?? 0,
            //         'provider_payment_method_id' => $company['provider_payment_method_id'] ?? null,
            //         'save_card' => $company['save_card'] ?? false,
            //         'action' => $company['action'] ?? 'subscribe',
            //     ]);
            // }


            // DB::commit();
            // $userCompanies = $user->companies;
            // return JsonResponser::send(false, 'Company created successfully', $userCompanies);
            return JsonResponser::send(false, 'Company validated successfully', $request->all());
        } catch (BadRequestException $e) {
            // DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            // DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function inviteUsers(InviteUsersRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::find($id);
            if (!$user) {
                throw new BadRequestException("User doesn't exist", 404);
            }
            $user->update([
                'onboarding_completed' => true
            ]);

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


    public function completeOnboarding(CompanyUserRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::find($id);
            if (!$user) {
                throw new BadRequestException("User doesn't exist", 404);
            }
            $user->update([
                'onboarding_completed' => true,
                'can_login' => true,
                'is_active' => true
            ]);

            if (
                (isset($request->companies) &&
                    count($request->companies) > 1) &&
                $user->company_type != CustomerTypeEnums::ACCOUNTANT->value
            ) {
                throw new BadRequestException("Multiple companies not allowed for small business", 400);
            }

            foreach ($request->companies as $key => $companyData) {
                $company = $this->companyService->create($companyData, $id);

                // ProcessCompanyOnboarding::dispatch($company);
                (new CoaProvisionerFromConfig())
                    ->provisionForCompany($company->id, $id);

                if ($key === array_key_first($request->companies)) {
                    $country = \Nnjeim\World\Models\Country::find($companyData['country_id']);
                    $user->update([
                        "currency_id" => $user->currency_id ?? $country->currency->id,
                        "country_id" => $user->country_id ?? $country->id,
                        "current_company_id" => $company->id
                    ]);
                }

                $user->companies()->attach($company->id, ['company_type' => $user->company_type, "uei_id" => (string) Str::uuid()]);
                $company->currencies()->attach($user->currency_id);
                $company->attachEmailTemplates();

                if (isset($companyData['subscription_plan_id']) && $companyData['subscription_plan_id']) {
                    $planId = $companyData['subscription_plan_id'];
                    $free = false;
                    $duration = $companyData['duration'];
                } else {
                    $freePlan = SubscriptionPlan::where('is_free', true)->first();
                    $planId = $freePlan->id;
                    $free = true;
                    $duration = $freePlan->duration > 30 ? 'yearly' : 'monthly';
                }
                $this->companySubscriptionService->subscribeToPlan([
                    'user_id' => $id,
                    'company_id' => $company->id,
                    'subscription_plan_id' => $planId,
                    'is_free' => $free,
                    'duration' => $duration,
                    'additional_users_count' => $companyData['additional_users_count'] ?? 0,
                    'provider_payment_method_id' => $companyData['provider_payment_method_id'] ?? null,
                    'save_card' => $companyData['save_card'] ?? false,
                    'action' => $companyData['action'] ?? 'subscribe',
                ]);
            }

            foreach ($request->users as $user) {
                $this->userService->create($user, null, $id);
            }

            DB::commit();
            return JsonResponser::send(false, 'Company created and invite sent successfully');
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function companyCheck(Request $request)
    {
        try {
            $response = CompanyHouseApi::companySearch($request->query('q'), $request->query('limit'), $request->query('offset'));
            return JsonResponser::send(false, 'Company search successful', CompanyHouseCompanyResource::collection($response['items']));
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }
}
