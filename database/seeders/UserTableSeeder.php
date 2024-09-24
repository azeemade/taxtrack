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
                'email' => fake()->safeEmail,
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@')
            ],
            [
                'name' => 'Admin',
                'email' => fake()->safeEmail,
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@')
            ],
            [
                'name' => 'Developer',
                'email' => fake()->safeEmail,
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@')
            ],
        ];
        $this->createUser($adminUsers);

        $companies = Company::get();
        $companyUsers = [];
        foreach ($companies as  $company) {
            $companyUsers[] = [
                'name' => fake()->firstName . ' ' . fake()->lastName,
                'email' => fake()->safeEmail,
                'phone_number' => fake()->phoneNumber,
                'password' => Hash::make('Password1@'),
                'company_id' => $company['id'],
                'created_by' => User::where('name', 'Admin')->first()->id
            ];
        }
        $this->createUser($companyUsers);
    }

    protected function createUser(array $users): void
    {
        foreach ($users as  $user) {
            $user = User::create($user);
            if ($user->has('company')) {
                $role = $this->findRole('client');
                $user->assignRole($role);
            } else {
                $role = $this->findRole($user->name);
                $user->assignRole($role);
            }
        }
    }

    protected function findRole(string $roleName): Role
    {
        return Role::where('name', $roleName)->first();
    }
}
