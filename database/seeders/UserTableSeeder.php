<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUsers = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@api.bookcalc.com',
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@'),
                'role' => 'super admin'
            ],
            [
                'name' => 'Developer',
                'email' => 'developer@api.bookcalc.com',
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@'),
                'role' => 'developer'
            ],
        ];
        $this->createUser($adminUsers);

        // $companies = Company::get();
        // $companyUsers = [];
        // foreach ($companies as  $company) {
        //     $companyUsers[] = [
        //         'name' => fake()->firstName . ' ' . fake()->lastName,
        //         'email' => fake()->safeEmail,
        //         'phone_number' => fake()->phoneNumber,
        //         'password' => Hash::make('Password1@'),
        //         'current_company_id' => $company['id'],
        //         // 'company_id' => $company['id'],
        //         'created_by' => User::where('name', 'Admin')->first()->id
        //     ];
        // }
        // $this->createUser($companyUsers);
    }

    protected function createUser(array $users): void
    {
        foreach ($users as  $user) {
            $role = $user['role'];
            unset($user['role']);
            $user = User::create($user);
            if ($user->has('company')) {
                $role = $this->findRole('client');
                $user->assignRole($role);
            } else {
                $role = $this->findRole($role);
                $user->assignRole($role);
            }
        }
    }

    protected function findRole(string $roleName): Role
    {
        return Role::where('name', $roleName)->first();
    }
}
