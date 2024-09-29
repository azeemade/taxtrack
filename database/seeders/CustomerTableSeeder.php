<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\Currency;

class CustomerTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = User::has('company')->inRandomOrder()->first();
        $country = Country::inRandomOrder()->first();
        $city = City::where('country_id', $country->id)->inRandomOrder()->first();
        $currency = Currency::where('country_id', $country->id)->inRandomOrder()->first();
        for ($i = 0; $i < 5; $i++) {
            Customer::create([
                'full_name' => fake()->firstName . ' ' . fake()->lastName,
                'display_name' => fake()->word,
                'salutation' => fake()->title,
                'customerID' => 'CUS-' . fake()->numberBetween($min = 1000, $max = 9000),
                'type' => fake()->randomElement($array = array('business', 'individual')),
                'created_by' => $client->id,
                'company_id' => $client->company_id,
                'primary_phone_ext' => $country->phone_code,
                'primary_phone_number' => fake()->phoneNumber,
                'primary_email' => fake()->safeEmail,
                'currency_id' => $currency->id,
                'country_id' => $country->id,
                'city_id' => $city->id,
                'primary_address' => fake()->address,
                'zip_code' => fake()->postcode,
                'is_active' => true
            ]);
        }
    }
}
