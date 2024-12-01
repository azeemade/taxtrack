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
            ["table" => "customers", "name" => "large-enterprise"],
            ["table" => "line_items", "name" => "hardware"],
            ["table" => "line_items", "name" => "software"],
            ["table" => "line_items", "name" => "materials"],
            ["table" => "line_items", "name" => "equipment"],
            ["table" => "line_items", "name" => "consulting"],
            ["table" => "line_items", "name" => "labor"],
            ["table" => "line_items", "name" => "maintenance"],
            ["table" => "line_items", "name" => "installation"],
            ["table" => "line_items", "name" => "project_management"],
            ["table" => "line_items", "name" => "design"],
            ["table" => "line_items", "name" => "development"],
            ["table" => "line_items", "name" => "testing"],
            ["table" => "line_items", "name" => "travel"],
            ["table" => "line_items", "name" => "training"],
            ["table" => "line_items", "name" => "marketing"],
            ["table" => "line_items", "name" => "utilities"],
            ["table" => "line_items", "name" => "subscription"],
            ["table" => "line_items", "name" => "licensing"],
            ["table" => "line_items", "name" => "delivery"],
            ["table" => "line_items", "name" => "miscellaneous"],
        ];
        foreach ($data as $value) {
            Category::updateOrCreate([
                "name" => $value["name"],
                "table" => $value["table"],
            ], [
                "name" => $value["name"],
                "table" => $value["table"],
            ]);
        }
    }
}
