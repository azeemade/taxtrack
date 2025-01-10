<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyEmailTemplate extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $emailTemplatesId = EmailTemplate::all()->pluck('id');

        foreach ($companies as $company) {
            $company->emailTemplates()->sync($emailTemplatesId);
        }
    }
}
