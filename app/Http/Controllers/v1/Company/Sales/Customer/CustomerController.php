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
            $records = $this->customerService->list($request->validated());
            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
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
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function createIndividualCustomer(CreateIndividualRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->only('category_id', 'customer_type', 'currency_id'),
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
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function createOrganizationCustomer(CreateOrganizationRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->validated(),
                "customer_type" => CustomerTypeEnums::ORGANIZATION->value,
                "customer_logo" => $request->image,
                "contact_persons" => $request->contact_persons
            ];
            $record = $this->customerService->createCustomer($data);

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
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }

    public function delete($id)
    {
        try {
            $record = $this->customerService->view($id);
            $record->delete();
            return JsonResponser::send(false, 'Customer status updated successfully');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500, $th);
        }
    }
}
