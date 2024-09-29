<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [['name' => 'access admin app', 'name' => 'access client app']];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate([
                'name' => $permission['name']
            ], [
                'guard_name' => 'api'
            ]);
        }
    }
}
