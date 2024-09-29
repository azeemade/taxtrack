<?php

namespace Database\Seeders;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\PaymentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LineItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //create customer, category
        $client = User::has('company')->inRandomOrder()->first();
        $customer = Customer::where('company_id', $client->company_id)->first();

        for ($i = 0; $i < 2; $i++) {
            $invoice = Invoice::create([
                'invoiceID' => 'INV-' . fake()->numberBetween($min = 1000, $max = 9000),
                'referenceID' => fake()->numberBetween($min = 1000000, $max = 9000000),
                'start_date' => fake()->date(),
                'due_date' => fake()->date(),
                'terms_and_conditions' => fake()->text($maxNbChars = 200),
                'customer_note' => fake()->text($maxNbChars = 100),
                'sub_total' => fake()->randomFloat($nbMaxDecimals = 2, $min = 20000, $max = 30000),
                'shipping_charge' => fake()->randomFloat($nbMaxDecimals = 2, $min = 200, $max = 300),
                'additional_charge' => fake()->randomFloat($nbMaxDecimals = 2, $min = 0, $max = 300),
                'status' => FinancialDocumentStatusEnums::ISSUED->value,
                'invoice_value' => fake()->randomFloat($nbMaxDecimals = 2, $min = 40000, $max = 50000),
                'description' => fake()->text($maxNbChars = 150),
                'payment_status' => PaymentStatusEnums::PENDING->value,
                'customer_id' => $customer->id,
                'currency_id' => $customer->currency_id,
                'created_by' => $client->id,
                'company_id' => $client->company_id
            ]);
            for ($i = 0; $i < 2; $i++) {
                LineItem::create([
                    'documentable_id' => $invoice->id,
                    'documentable_type' => 'App\Models\Invoice',
                    'item_details' => fake()->text($maxNbChars = 70),
                    'quantity' => fake()->numberBetween($min = 1, $max = 9),
                    'price' => fake()->randomFloat($nbMaxDecimals = 2, $min = 2000, $max = 30000),
                    'discount' => fake()->randomFloat($nbMaxDecimals = 2, $min = 200, $max = 300),
                    'vat' => fake()->randomFloat($nbMaxDecimals = 2, $min = 20, $max = 50),
                    'amount' => fake()->randomFloat($nbMaxDecimals = 2, $min = 45000, $max = 90000),
                    'created_by' => $client->id,
                    'company_id' => $client->company_id
                ]);
            }
        }
    }
}
