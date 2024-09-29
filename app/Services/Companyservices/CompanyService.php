<?php

namespace App\Services\CompanyServices;

use App\Enums\CompanyStatusEnums;
use App\Helpers\GeneralHelper;
use App\Models\Company;

class CompanyService
{
    public function create(array $data)
    {
        $company = Company::create([
            'name' => $data['name'],
            'address' => $data['address'],
            'phone_number' => $data['phone_number'],
            'companyUUID' => GeneralHelper::generateCompanyUUID(),
            'domain' => $data['domain'],
            'status' => CompanyStatusEnums::PENDING->value
        ]);

        return $company;
    }
}
