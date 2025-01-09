<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $emailTemplates = [
            [
                'name' => 'Credit Note',
            ],
            [
                'name' => 'Purchase Order',
            ],
            [
                'name' => 'Quote',
            ],
            [
                'name' => 'Sales Invoice',
            ],
            [
                'name' => 'Purchase Invoice',
            ],
            [
                'name' => 'Recurring Invoice',
            ],
            [
                'name' => 'Debit Note',
            ],
            [
                'name' => 'Receipt',
            ]
        ];
        foreach ($emailTemplates as $template) {
            EmailTemplate::updateOrCreate(["slug" => Str::slug($template['name'])], ["name" => $template['name'], "slug" => Str::slug($template['name'])]);
        }
    }
}
