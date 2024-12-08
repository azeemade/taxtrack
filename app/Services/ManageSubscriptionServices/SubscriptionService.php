<?php

namespace App\Services\ManageSubscriptionServices;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Mail\Company\ClientOnboardingEmail;
use App\Models\Module;
use App\Models\Role;
use App\Models\SubscriptionFunctionality;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SubscriptionService
{

    public function overview($request)
    {
        $currentUser = Auth::user();
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->where('created_by', $currentUser->id)
            ->with('roles:id,roleID,name', 'author:id,name')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->q . '%');
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            })
            ->when($request->startDate && $request->endDate, function ($query) use ($request) {
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

    public function stats($request)
    {
        $currentUser = Auth::user();
        $dateFilter = GeneralHelper::dateFilter($request->date_filter);

        $records = User::query()
            ->where('created_by', $currentUser->id)
            ->when($dateFilter, function ($query) use ($dateFilter) {
                return $query->where('created_at', '>=', $dateFilter);
            });

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('status', GeneralEnums::ACTIVE->value)->count(), // Count active records
            'inactive' => (clone $records)->where('status', GeneralEnums::INACTIVE->value)->count(), // Count inactive records
        ];
    }

    public function export($records)
    {
        $recordHeadings = ['ID', 'Name', 'Email Address', 'Status', 'Role Name', 'Created By'];
        $records = $records->map(function ($record) {
            return [
                $record->id,
                $record->name,
                $record->email,
                $record->status,
                optional($record->roles->first())->name ?? 'No Role Assigned',
                $record->author->name
            ];
        });
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'admin_users_report.xlsx');
    }

    public function create($data)
    {
        $currentUser = auth()->user();

        $plan = SubscriptionPlan::create([
            'title' => $data['title'],
            'monthly_fee' => $data['monthly_fee'],
            'yearly_fee' => $data['yearly_fee'],
            'short_description' => $data['short_description'],
            'primary_cta_text' => $data['primary_cta_text'],
            'primary_link' => $data['primary_link'],
            'secondary_cta' => $data['secondary_cta'],
            'secondary_link' => $data['secondary_link'],
            'created_by' => $currentUser->id
        ]);
        // dd($data['title']);

        if (isset($data['features'])) {
            foreach ($data['features'] as $title) {
                $subscriptionPlanFeature = SubscriptionPlanFeature::create([
                    'title' => $title,
                    'subscription_plan_id' => $plan->id
                ]);
            }
        }

        if (isset($data['modules'])) {
            foreach ($data['modules'] as $module) {
                $subscriptionFunctionality = SubscriptionFunctionality::create([
                    'subscription_plan_id' => $plan->id,
                    'module_id' => $module['module_id'],
                    'module_functionality_id' => $module['module_functionality_id']
                ]);
            }
        }

        return $plan;
    }

    public function update($data, $user)
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
        ]);

        $user->syncRoles([$data['roles']]);
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

    public function module()
    {
        $records = Module::with('moduleFunctionality')->orderBy('id', 'DESC');

        if (!$records) {
            throw new BadRequestException('Role not found', 404);
        }

        return $records->get();
    }
}
