<?php

namespace Database\Seeders;

use App\Enums\EmailTemplateModelEnums;
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
                'name' => EmailTemplateModelEnums::CREDIT_NOTE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::PURCHASE_ORDER->value,
            ],
            [
                'name' => EmailTemplateModelEnums::QUOTE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::SALES_INVOICE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::PURCHASE_INVOICE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::RECURRING_INVOICE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::DEBIT_NOTE->value,
            ],
            [
                'name' => EmailTemplateModelEnums::RECEIPT->value,
            ]
        ];
        foreach ($emailTemplates as $template) {
            EmailTemplate::updateOrCreate(["slug" => Str::slug($template['name'])], ["name" => $template['name'], "slug" => Str::slug($template['name'])]);
        }
    }
}
