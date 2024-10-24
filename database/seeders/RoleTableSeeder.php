<?php

namespace Database\Seeders;

use App\Helpers\GeneralHelper;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['super admin', 'admin', 'developer', 'client', 'company admin', 'company user'];
        $roleID = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => 'Spatie\Permission\Models\Role',
            'modelField' => 'roleID',
            'prefix' => 'R-',
            'idLength' => 3
        ]);

        foreach ($roles as $role) {
            $record = Role::firstOrCreate([
                'name' => $role,
            ], [
                'name' => $role,
                'slug' => Str::slug($role, ''),
                'roleID' => $roleID,
                'guard_name' => 'api'
            ]);
            if (!in_array($role, ['client', 'company admin', 'company user'])) {
                $record->givePermissionTo('access admin app');
            } else {
                $record->givePermissionTo('access client app');
            }
        }
    }
}
