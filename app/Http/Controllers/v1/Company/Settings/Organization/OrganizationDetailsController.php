<?php

namespace App\Http\Controllers\v1\Company\Settings\Organization;

use App\Enums\CustomerTypeEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AddCompanyRequest;
use App\Http\Requests\Company\Settings\Organization\UpdateOrganizationDetailsRequest;
use App\Responser\JsonResponser;
use App\Services\Company\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrganizationDetailsController extends Controller
{
    protected CompanyService $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    /**
     * Display the specified resource.
     */
    public function view()
    {
        try {
            $record = $this->companyService->companyDetails();

            return JsonResponser::send(false, 'Record retrieved successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationDetailsRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->companyService->updateCompanyDetails($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Company details updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function updateVATScheme(UpdateOrganizationDetailsRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->companyService->updateCompanyVATDetails($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Company details updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }



    public function addCompany(AddCompanyRequest $request)
    {
        $validated = $request->validated();
        try {
            $user = Auth::user();

            if (
                $user->company_type != CustomerTypeEnums::ACCOUNTANT->value
            ) {
                throw new BadRequestException("Multiple companies not allowed for small business", 400);
            }

            foreach ($validated['companies'] as $company) {
                $company = $this->companyService->onboardCompany($company, $user);
            }

            return JsonResponser::send(false, 'Companies created successfully');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function listCompanies()
    {
        try {
            $companies = $this->companyService->listCompanies();
            return JsonResponser::send(false, 'Companies retrieved successfully', $companies);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }
}
