<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ["table" => "customers", "name" => "small-business"],
            ["table" => "customers", "name" => "medium-sized-business"],
            ["table" => "customers", "name" => "large-enterprise"]
        ];
        foreach ($data as $value) {
            Category::create([
                "name" => $value["name"],
                "table" => $value["table"],
            ]);
        }
    }
}
