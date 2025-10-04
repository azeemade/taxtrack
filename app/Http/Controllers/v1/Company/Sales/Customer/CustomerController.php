<?php

namespace App\Http\Controllers\v1\Company\Sales\Customer;

use App\Enums\CustomerTypeEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Sales\Customer\CreateIndividualRequest;
use App\Http\Requests\Company\Sales\Customer\CreateOrganizationRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\Customer\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function overview(SharedFilterRequest $request)
    {
        try {
            $records = $this->customerService->list($request);
            if ($request->export) {
                return $this->customerService->export($records, $request->export);
            }

            $stats = $this->customerService->stats($request);
            $records = [
                ...$stats,
                'data' => $records
            ];

            if (!$request["paginate"]) {
                $records = $records["data"];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function view($id)
    {
        try {
            $record = $this->customerService->view($id);
            return JsonResponser::send(false, 'Record found successfully', $record);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function createIndividualCustomer(CreateIndividualRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->only(
                    'category_id',
                    'customer_type',
                    'currency_id',
                    'phone_ext',
                    'payment_term',
                    'terms_and_conditions',
                    'vat_number',
                    'vat_date',
                    'county'
                ),
                "company_name" => $request->display_name,
                "customer_logo" => $request->image,
                "phone_number" => $request->primary_phone_number,
                "email" => $request->primary_email,
                "country_id" => $request->country_id,
                "city_id" => $request->city_id,
                "zip_code" => $request->zip_code,
                "address" => $request->primary_address,
                "contact_persons" => [[
                    "full_name" => $request->full_name,
                    "salutation" => $request->salutation,
                    "primary_phone_number" => $request->primary_phone_number,
                    "secondary_phone_number" => $request->secondary_phone_number,
                    "primary_email" => $request->primary_email,
                    "secondary_email" => $request->secondary_email,
                    "country_id" => $request->country_id,
                    "city_id" => $request->city_id,
                    "primary_address" => $request->primary_address,
                    "secondary_address" => $request->secondary_address,
                    "post_code" => $request->zip_code
                ]]
            ];
            $record = $this->customerService->createCustomer($data);

            DB::commit();
            return JsonResponser::send(false, 'Individual customer created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function createOrganizationCustomer(CreateOrganizationRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->only(
                    'company_name',
                    'category_id',
                    'customer_type',
                    'currency_id',
                    'phone_ext',
                    'address',
                    'city_id',
                    'state_id',
                    'country_id',
                    'city_id',
                    'terms_and_conditions',
                    'vat_number',
                    'vat_date',
                    'county'
                ),
                "customer_logo" => $request->image,
                "email" => $request->primary_email,
                "phone_number" => $request->primary_phone_number,
                "customer_type" => CustomerTypeEnums::ORGANIZATION->value,
                "customer_logo" => $request->image,
                "contact_persons" => $request->contact_persons
            ];
            $record = $this->customerService->createCustomer($data);

            DB::commit();
            return JsonResponser::send(false, 'Customer created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function updateIndividualCustomer(CreateIndividualRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->only(
                    'category_id',
                    'customer_type',
                    'currency_id',
                    'phone_ext',
                    'payment_term',
                    'terms_and_conditions',
                    'vat_number',
                    'vat_date',
                    'tax_type',
                    'county'
                ),
                "id" => $id,
                "company_name" => $request->display_name,
                "customer_logo" => $request->image,
                "phone_number" => $request->primary_phone_number,
                "email" => $request->primary_email,
                "country_id" => $request->country_id,
                "city_id" => $request->city_id,
                "zip_code" => $request->zip_code,
                "address" => $request->primary_address,
                "contact_persons" => [[
                    "id" => $request->contact_person_id,
                    "full_name" => $request->full_name,
                    "salutation" => $request->salutation,
                    "primary_phone_number" => $request->primary_phone_number,
                    "secondary_phone_number" => $request->secondary_phone_number,
                    "primary_email" => $request->primary_email,
                    "secondary_email" => $request->secondary_email,
                    "country_id" => $request->country_id,
                    "city_id" => $request->city_id,
                    "primary_address" => $request->primary_address,
                    "secondary_address" => $request->secondary_address,
                    "post_code" => $request->zip_code
                ]]
            ];
            $record = $this->customerService->createCustomer($data);

            DB::commit();
            return JsonResponser::send(false, 'Customer updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function updateOrganizationCustomer(CreateOrganizationRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->only(
                    'company_name',
                    'category_id',
                    'customer_type',
                    'currency_id',
                    'phone_ext',
                    'address',
                    'city_id',
                    'state_id',
                    'country_id',
                    'city_id',
                    'terms_and_conditions',
                    'vat_number',
                    'vat_date',
                    'tax_type',
                    'county'
                ),
                "id" => $id,
                "customer_logo" => $request->image,
                "email" => $request->primary_email,
                "phone_number" => $request->primary_phone_number,
                "customer_type" => CustomerTypeEnums::ORGANIZATION->value,
                "customer_logo" => $request->image,
                "contact_persons" => $request->contact_persons
            ];
            $record = $this->customerService->createCustomer($data);

            DB::commit();
            return JsonResponser::send(false, 'Customer updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function changeStatus($id)
    {
        try {
            $record = $this->customerService->view($id);
            $record->update([
                "is_active" => !$record->is_active
            ]);
            return JsonResponser::send(false, 'Customer status updated successfully');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function delete($id)
    {
        try {
            $record = $this->customerService->view($id);
            $record->delete();
            return JsonResponser::send(false, 'Customer deleted successfully');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateCustomerStatement($id)
    {
        try {
            return  $this->customerService->generateCustomerStatement($id);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
