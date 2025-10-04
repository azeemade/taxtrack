<?php

namespace App\Http\Controllers\v1\Company\Purchase\Vendor;

use App\Enums\CustomerTypeEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Purchase\Supplier\CreateOrganizationRequest;
use App\Http\Requests\Company\Purchase\Supplier\CreateIndividualRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\Supplier\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VendorController extends Controller
{
    protected SupplierService $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->supplierService->list($request);
            if ($request->export) {
                return $this->supplierService->export($records, $request->export);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $record = $this->supplierService->view($id);
            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Generate reference for supplier
     */
    public function generateReference()
    {
        try {
            $record = $this->supplierService->generateSupplierReference();
            return JsonResponser::send(false, 'Reference generated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function createIndividualSupplier(CreateIndividualRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->validated(),
                "vendor_type" => "individual",
                "contact_persons" => [[
                    "full_name" => $request->vendor_name,
                    ...$request->validated()
                ]]
            ];
            $record = $this->supplierService->createSupplier($data);

            DB::commit();
            return JsonResponser::send(false, 'Supplier created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function createOrganizationSupplier(CreateOrganizationRequest $request)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->validated(),
                "vendor_type" => "organization",
                "contact_persons" => $request->contact_persons
            ];
            $record = $this->supplierService->createSupplier($data);

            DB::commit();
            return JsonResponser::send(false, 'Supplier created successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function updateIndividualSupplier(CreateIndividualRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = [
                ...$request->validated(),
                "id" => $id,
                "contact_persons" => [[
                    "full_name" => $request->vendor_name,
                    ...$request->validated()
                ]]
            ];
            $record = $this->supplierService->createSupplier($data);

            DB::commit();
            return JsonResponser::send(false, 'Individual supplier updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function updateOrganizationSupplier(CreateOrganizationRequest $request, $id)
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
            $record = $this->supplierService->createSupplier($data);

            DB::commit();
            return JsonResponser::send(false, 'Supplier updated successfully', $record);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
