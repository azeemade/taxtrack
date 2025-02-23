<?php

namespace Database\Seeders;

use App\Models\SubscriptionFunctionality;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SubscriptionPlan::create([
            'title' => 'Free plan',
            'monthly_fee' => 0.00,
            'yearly_fee' => 0.00,
            'short_description' => 'This is free plan',
            'primary_cta_text' => 'Continue with free plan',
            'primary_link' => '',
            'secondary_cta' => '',
            'secondary_link' => '',
            'created_by' => 1,
            'is_active' => true,
            'is_free' => true,
        ]);
    }
}
