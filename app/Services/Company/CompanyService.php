<?php

namespace App\Services\Company;

use App\Enums\CompanyStatusEnums;
use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
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
            'name' => $data['name'],
            'address' => $data['address'],
            'phone_number' => isset($data['phone_number']) ? $data['phone_number'] : null,
            'companyUUID' => GeneralHelper::generateCompanyUUID(),
            'domain' => isset($data['domain']) ? $data['domain'] : null,
            'status' => CompanyStatusEnums::APPROVED->value,
            'created_by' => auth()->user()?->id ?: $created_by
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

    public function updateCompanyDetails(array $data)
    {
        $currentUser = Auth::user();
        $record = Company::find($currentUser->current_company_id);

        if (isset($data['physical_address_information'])) {
            $data['address'] = $data['physical_address_information']['address'];
            $data['country_id'] = $data['physical_address_information']['country_id'];
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
