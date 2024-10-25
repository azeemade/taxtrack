<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Enums\PermissionEnums;
use App\Models\Role;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $role = Role::where('name', 'company admin')->first();
        // $role->givePermissionTo(Permission::where('app', 'client')->get());
        foreach (PermissionEnums::cases() as $permission) {
            Permission::updateOrCreate([
                'name' => $permission->value
            ], [
                'guard_name' => 'api',
                'app' => $permission->value == 'access_admin_app' ? 'app' : 'client',
                'module' => in_array($permission->value, [
                    "access_dashboard_module",
                    'access_sales_module',
                    'access_purchase_module',
                    'access_banking_module',
                    'access_accounting_module',
                    'access_tools_module',
                    'access_budget_module',
                    'access_report_module',
                    'access_user_management_module'
                ]) ? null : PermissionEnums::getModuleByPermission($permission->value),
                'submodule' => in_array($permission->value, [
                    'access_dashboard_module',
                    'access_sales_module',
                    'access_purchase_module',
                    'access_banking_module',
                    'access_accounting_module',
                    'access_tools_module',
                    'access_budget_module',
                    'access_report_module',
                    'access_user_management_module'
                ]) ? null : PermissionEnums::getSubmoduleByPermission($permission->value),
            ]);
        }
    }
}
