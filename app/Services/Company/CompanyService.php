<?php

namespace App\Services\Company;

use App\Enums\CompanyStatusEnums;
use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class CompanyService
{
    public function overview($request)
    {
        $records = Company::query()
            ->withCount('staff as staff_count')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            })
            ->when($request->sortBy == 'alphabetically', function ($query) {
                $query->orderBy('name', 'ASC');
            });

        if ($request->paginate && !$request->export) {
            return $records->paginate($request->limit);
        }
        return $records->get();
    }

    public function companyDetails()
    {
        $currentUser = Auth::user();
        $record = Company::select(
            'name',
            'phone_country_code',
            'phone_number',
            'secondary_phone_country_code',
            'secondary_phone_number',
            'fax',
            'industry',
            'email',
            'secondary_email',
            'organization_type',
            'description',
            'logo',
            'postal_address_information',
            'physical_address_information',
            'social_media',
            'registration_id',
            'terms_and_conditions',
        )
            ->find($currentUser->current_company_id);

        $record['postal_address_information'] = [
            "country" => isset($record['postal_address_information']['country_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\Country', $record['postal_address_information']['country_id']) : null,
            "city" => isset($record['postal_address_information']['city_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\City', $record['postal_address_information']['city_id']) : null,
            "state" => isset($record['postal_address_information']['state_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\State', $record['postal_address_information']['state_id']) : null,
        ];
        $record['physical_address_information'] = [
            "country" => isset($record['physical_address_information']['country_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\Country', $record['physical_address_information']['country_id']) : null,
            "city" => isset($record['physical_address_information']['city_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\City', $record['physical_address_information']['city_id']) : null,
            "state" => isset($record['physical_address_information']['state_id']) ? $this->getCompanyAddressInfo('\Nnjeim\World\Models\State', $record['physical_address_information']['state_id']) : null,
        ];
        return $record;
    }

    public function stats()
    {
        $records = Company::query();

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('is_active', true)->count(), // Count active records
            'inactive' => (clone $records)->where('is_active', false)->count(), // Count inactive records
            'pending' => (clone $records)->where('status', GeneralEnums::PENDING->value)->count(), // Count active records
            'approved' => (clone $records)->where('status', GeneralEnums::APPROVED->value)->count(), // Count inactive records
            'suspended' => (clone $records)->where('status', GeneralEnums::SUSPENDED->value)->count(), // Count inactive records
            'declined' => (clone $records)->where('status', GeneralEnums::DECLINED->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['id', 'Name', 'Staff count', 'Status', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->companyUUID,
                $record->name,
                $record->staff_count,
                $record->status,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'company_report.xlsx');
    }

    public function create(array $data, int $created_by = null)
    {
        return Company::create([
            ...$data,
            'name' => $data['name'],
            'address' => $data['address'],
            'phone_number' => isset($data['phone_number']) ? $data['phone_number'] : null,
            'companyUUID' => GeneralHelper::generateCompanyUUID(),
            'domain' => isset($data['domain']) ? $data['domain'] : null,
            'status' => CompanyStatusEnums::APPROVED->value,
            'created_by' => Auth::user()?->id ?: $created_by
        ]);
    }

    public function attachUser($request)
    {
        $company = Company::find($request['company_id']);

        $password = isset($request['password']) ? $request['password'] : Str::slug($company->name) . rand(100, 999);
        $user = User::create([
            'name' => $request['name'],
            'email' => $request['email'],
            'phone_number' => $request['phone_number'],
            'password' => Hash::make($password),
            'created_by' => Auth::id(),
            'company_id' => $request['company_id'],
            'contact_person' => $request['contact_person']
        ]);
        $user->assignRole('client');

        $data = [
            'entity_name' => $request['name'],
            'email' => $request['email'],
            'password' => $password,
        ];

        Mail::to($request['email'])
            ->send(new ClientOnboardingEmail($data));
        return $user;
    }

    // public function updateCompanyDetails(array $data)
    // {
    //     $currentUser = Auth::user();
    //     $record = Company::find($currentUser->current_company_id);

    //     if (isset($data['physical_address_information'])) {
    //         $data['address'] = $data['physical_address_information']['address'] ?? $record['physical_address_information']['address'];
    //         $data['physical_address_information']['address'] = $data['physical_address_information']['address'] ?? $record['physical_address_information']['address'];
    //         $data['physical_address_information']['country_id'] = $data['physical_address_information']['country_id'] ?? $record['physical_address_information']['country_id'];
    //         $data['physical_address_information']['state_id'] = $data['physical_address_information']['state_id'] ?? $record['physical_address_information']['state_id'];
    //         $data['physical_address_information']['city_id'] = $data['physical_address_information']['city_id'] ?? $record['physical_address_information']['city_id'];
    //     }
    //     if (isset($data['postal_address_information'])) {
    //         $data['postal_address_information']['address'] = $data['postal_address_information']['address'] ?? $record['postal_address_information']['address'];
    //         $data['postal_address_information']['country_id'] = $data['postal_address_information']['country_id'] ?? $record['postal_address_information']['country_id'];
    //         $data['postal_address_information']['state_id'] = $data['postal_address_information']['state_id'] ?? $record['postal_address_information']['state_id'];
    //         $data['postal_address_information']['city_id'] = $data['postal_address_information']['city_id'] ?? $record['postal_address_information']['city_id'];
    //     }

    //     $record->update($data);
    //     return $record;
    // }

    public function updateCompanyDetails(array $data)
    {
        $currentUser = Auth::user();
        $record = Company::find($currentUser->current_company_id);

        // Check if the company record exists
        if (!$record) {
            throw new BadRequestException('Company not found', Response::HTTP_NOT_FOUND);
        }

        // Decode JSON fields if they are stored as JSON in the database

        $postal_address = is_array($data['postal_address_information'])
            ? $data['postal_address_information']
            : json_decode($data['postal_address_information'], true);

        $physical_address = is_array($data['physical_address_information'])
            ? $data['physical_address_information']
            : json_decode($data['physical_address_information'], true);

        $physicalAddressInfo = $physical_address ? $physical_address : [];
        $postalAddressInfo = $postal_address ? $postal_address : [];

        // Handle physical_address_information
        if (isset($data['physical_address_information'])) {
            $data['address'] = $data['physical_address_information']['address'] ?? ($physicalAddressInfo['address'] ?? null);
            $data['physical_address_information']['address'] = $data['physical_address_information']['address'] ?? ($physicalAddressInfo['address'] ?? null);
            $data['physical_address_information']['country_id'] = $data['physical_address_information']['country_id'] ?? ($physicalAddressInfo['country_id'] ?? null);
            $data['physical_address_information']['state_id'] = $data['physical_address_information']['state_id'] ?? ($physicalAddressInfo['state_id'] ?? null);
            $data['physical_address_information']['city_id'] = $data['physical_address_information']['city_id'] ?? ($physicalAddressInfo['city_id'] ?? null);
            $data['physical_address_information']['phone_number'] = $data['physical_address_information']['phone_number'] ?? ($physicalAddressInfo['phone_number'] ?? null);
        }

        // Handle postal_address_information
        if (isset($data['postal_address_information'])) {
            $data['postal_address_information']['address'] = $data['postal_address_information']['address'] ?? ($postalAddressInfo['address'] ?? null);
            $data['postal_address_information']['country_id'] = $data['postal_address_information']['country_id'] ?? ($postalAddressInfo['country_id'] ?? null);
            $data['postal_address_information']['state_id'] = $data['postal_address_information']['state_id'] ?? ($postalAddressInfo['state_id'] ?? null);
            $data['postal_address_information']['city_id'] = $data['postal_address_information']['city_id'] ?? ($postalAddressInfo['city_id'] ?? null);
            $data['postal_address_information']['phone_number'] = $data['postal_address_information']['phone_number'] ?? ($postalAddressInfo['phone_number'] ?? null);
        }

        $record->update($data);
        return $record;
    }

    protected function getCompanyAddressInfo($model, $id)
    {
        $model = $model::select('id', 'name')->find($id);
        return $model;
    }
}
