<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleFunctionality;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ModuleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            'dashboard' => ['access dashboard'],
            'sales' => ['access customer', 'access quote', 'access invoice', 'access credit note'],
            'purchase' => ['access vendor', 'access purchase order', 'access purchase invoice', 'access vendor bill', 'access debit note'],
            'banking' => ['access card', 'access bank', 'access transaction', 'access reconciliation'],
            'accounting' => ['access journal entry', 'access chart of account'],
            'budget' => ['access budget'],
            'tools' => ['access tool'],
            'report' => ['access report'],
            'user management' => ['access manage users', 'access manage roles'],
            'settings' => ['access email settings', 'access invoice settings', 'access tax rate'],
            'audit trail' => ['access audit trail'],
        ];

        foreach ($modules as $key => $module) {
            $newModule = Module::firstOrCreate([
                'name' => Str::title($key),
            ], [
                'name' => Str::title($key),
            ]);

            foreach ($module as $functionality) {
                ModuleFunctionality::firstOrCreate(
                    [
                        'name' => $functionality,
                        'module_id' => $newModule->id
                    ],
                    [
                        'name' => $functionality,
                        'module_id' => $newModule->id
                    ]
                );
            }
        }
    }
}
