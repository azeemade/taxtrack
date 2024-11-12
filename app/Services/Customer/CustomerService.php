<?php

namespace App\Services\Customer;

use App\Enums\GeneralEnums;
use App\Helpers\GeneralHelper;
use App\Models\Customer;

class CustomerService
{
    public function list($request)
    {
        return Customer::query()
            ->with('contactPerson')
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('company_name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                if ($request->status == GeneralEnums::ACTIVE->value) {
                    return $query->where('is_active', true);
                } else {
                    return $query->where('is_active', false);
                }
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('company_name', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('customerID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('contactPerson', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->latest()
            ->paginate($request->limit);
    }

    public function view($id)
    {
        return Customer::find($id);
    }

    public function createCustomer($request)
    {
        $record = Customer::create([
            "company_name" => $request->company_name,
            "customerID" => $this->generateCompanyReference(),
            "business_registration_number" => $request->business_registration_number,
            "vat_number" => $request->vat_number,
            "category_id" => $request->category_id,
            "customer_type" => $request->customer_type,
            "business_type" => $request->business_type,
            "industry" => $request->industry,
            "employee_count" => $request->employee_count,
            "currency_id" => $request->currency_id,
            "customer_logo" => $request->customer_logo,
        ]);

        $this->createContactPerson($record, $request);
    }

    protected function createContactPerson($record, $request)
    {
        foreach ($request->contact_persons as $person) {
            $record->addContactPerson([
                "full_name" => $person['full_name'],
                "salutation" => $person['salutation'],
                "primary_email" => $person['primary_email'],
                "secondary_email" => $person['secondary_email'],
                "primary_phone_number" => $person['primary_phone_number'],
                "secondary_phone_number" => $person['secondary_phone_number'],
                "country_id" => $person['country_id'],
                "state_id" => $person['state_id'],
                "city_id" => $person['city_id'],
                "primary_address" => $person['primary_address'],
                "secondary_address" => $person['secondary_address'],
                "post_code" => $person['post_code'],
            ]);
        }
    }

    protected function generateCompanyReference()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Customer',
            "modelField" => 'customerID',
            "prefix" => 'c-',
            "idLength" => 3,
        ]);
    }
}
