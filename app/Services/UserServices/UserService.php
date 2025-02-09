<?php

namespace App\Services\UserServices;

use App\Enums\GeneralEnums;
use App\Exports\GeneralReportExport;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Company;
use App\Models\User;
use App\Services\RoleServices\RoleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{

    protected RoleService $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function overview($request)
    {
        $currentUser = Auth::user();
        $currentUserCompany = $currentUser?->company;

        $records = User::query()
            ->whereRelation('companies', 'company_id', $currentUserCompany?->id)
            ->with('roles:id,roleID,name')
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

    public function stats()
    {
        $currentUser = Auth::user();
        $currentUserCompany = $currentUser?->company;

        $records = User::query()
            ->whereRelation('companies', 'company_id', $currentUserCompany?->id);

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(), // Count inactive records
        ];
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['ID', 'Name', 'Status', 'Role ID', 'Role', 'No of permissions', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->name,
                $record->status,
                $record->role->roleID ?? null,
                $record->role->name ?? null,
                $record->permissions_count ?? 0,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });
        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'role_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'role_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function create(array $data, int $company_id = null, int $created_by = null)
    {
        $currentUser = auth()->user();
        if (isset($data['company'])) {
            $companyField = $data['company'][0];
        }

        $company = Company::query();
        if (isset($companyField) && $companyField) {
            $company->where('name', $companyField)
                ->orWhere('id', $companyField);
        }
        if (isset($company_id) && $company_id) {
            $company->where('id', $company_id);
        }
        $company = $company->first();

        $currentUserCompany = $currentUser?->company ?: $company;

        $password = isset($data['password']) ? $data['password'] : Str::slug($currentUserCompany->name) . rand(100, 999);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_number' => isset($data['phone_number']) ? $data['phone_number'] : null,
                'password' => Hash::make($password),
                'created_by' => $currentUser?->id ?: $created_by,
                'current_company_id' => $currentUserCompany->id
            ]);
        }

        $user->assignRole(['client', 'company user']);

        if (is_array($data['roles'])) {
            foreach ($data['roles'] as $role) {
                if (is_numeric($role) && (int)$role == $role) {
                    $user->assignRole($role);
                } else {
                    $role = $this->roleService->create(["name" => $role]);
                    $user->assignRole($role);
                }
            }
        } else {
            $user->assignRole($data['roles']);
        }

        //TODO: consider user role for different companies
        $cid = isset($data['company_id']) ? $data['company_id'] : $currentUserCompany->id;
        $user->companies()->attach($cid, ["uei_id" => (string) Str::uuid()]);

        $data = [
            'entity_name' => $data['name'],
            'company_name' => $currentUserCompany->name,
            'email' => $data['email'],
            'password' => $password,
        ];

        Mail::to($data['email'])
            ->send(new ClientOnboardingEmail($data));

        return $user;
    }

    public function update(array $data, User $user)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
        ]);

        $user->assignRole($data['roles']);
        return $user;
    }

    public function toggle(User $user)
    {
        $user->update([
            'status' => $user->status == GeneralEnums::ACTIVE->value ? GeneralEnums::INACTIVE->value : GeneralEnums::ACTIVE->value
        ]);
        return $user;
    }

    public function delete(User $user)
    {
        $user->delete();
    }
}
