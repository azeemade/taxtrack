<?php

namespace Database\Seeders;

use App\Helpers\GeneralHelper;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['super admin', 'admin', 'developer', 'client'];
        $roleID = GeneralHelper::getModelUniqueOrderlyId([
            'modelNamespace' => 'Spatie\Permission\Models\Role',
            'modelField' => 'roleID',
            'prefix' => 'R-',
            'idLength' => 3
        ]);

        foreach ($roles as $role) {
            $record = Role::create([
                'name' => $role,
                'roleID' => $roleID,
                'guard_name' => 'api'
            ]);
            if ($role != 'client') {
                $record->givePermissionTo('access admin app');
            } else {
                $record->givePermissionTo('access client app');
            }
        }
    }
}
