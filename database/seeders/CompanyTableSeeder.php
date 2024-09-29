<?php

namespace Database\Seeders;

use App\Enums\CompanyStatusEnums;
use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Company::create([
                'name' => fake()->company,
                'address' => fake()->address,
                'phone_number' => fake()->phoneNumber,
                'companyUUID' => fake()->uuid,
                'domain' => fake()->domainName,
                'status' => CompanyStatusEnums::APPROVED->value
            ]);
        }
    }
}
