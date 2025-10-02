<?php

namespace Database\Seeders;

use App\Models\Bank;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Nnjeim\World\Models\Country;

class BanksTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $filePath = database_path('data/banks_database.csv');
            $parsedData = $this->parseCsvWithMaatwebsite($filePath);

            Bank::truncate();
            foreach ($parsedData as $key => $row) {
                if ($key == 0) continue;
                dump($row[2]); // Process each row as needed

                $data = $this->parseRow($row[2]);
                dump($data["name"], $data["country"]);
                Bank::create([
                    'name' => $data["name"],
                    'country_id' => Country::where('name', 'LIKE', '%' . $data["country"] . '%')
                        ->orWhere('iso2', 'LIKE', '%' . $data["country"] . '%')
                        ->first()?->id
                ]);
            }
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    protected function parseRow($input)
    {
        $pattern = '/^(.*?)\s\((.*?)-\s(.*?)\)$/';
        if (preg_match($pattern, $input, $matches)) {
            return [
                'name' => $matches[1], // Before the first "("
                'country' => $matches[3], // After the " - " and before the ")"
            ];
        }
    }
    
    public function parseCsvWithMaatwebsite($filePath)
    {
        $data = [];

        // Open the file
        $file = fopen($filePath, 'r');

        // Read the file line by line
        while (($row = fgetcsv($file, 1000, ",")) !== false) {
            $data[] = $row;
        }

        // Close the file
        fclose($file);

        return $data;
    }
}
