<?php

namespace Database\Seeders;

use App\Models\CardBrand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CardBrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            [
                "name" => "Visa",
                "code" => "visa",
                "type" => json_encode(["credit", "debit", "prepaid"])
            ],
            [
                "name" => "Mastercard",
                "code" => "mastercard",
                "type" => json_encode(["credit", "debit", "prepaid"])
            ],
            [
                "name" => "American Express",
                "code" => "amex",
                "type" => json_encode(["credit", "charge"])
            ],
            [
                "name" => "Discover",
                "code" => "discover",
                "type" => json_encode(["credit"])
            ],
            [
                "name" => "Diners Club International",
                "code" => "diners",
                "type" => json_encode(["credit", "charge"])
            ],
            [
                "name" => "JCB",
                "code" => "jcb",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "UnionPay",
                "code" => "unionpay",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "Verve",
                "code" => "verve",
                "type" => json_encode(["debit", "prepaid"])
            ],
            [
                "name" => "InterPay",
                "code" => "interpay",
                "type" => json_encode(["debit"])
            ],
            [
                "name" => "RuPay",
                "code" => "rupay",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "BC Card",
                "code" => "bccard",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "GPN",
                "code" => "gpn",
                "type" => json_encode(["debit"])
            ],
            [
                "name" => "Mir",
                "code" => "mir",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "Carte Bancaire",
                "code" => "cb",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "Bancontact",
                "code" => "bancontact",
                "type" => json_encode(["debit"])
            ],
            [
                "name" => "Girocard",
                "code" => "girocard",
                "type" => json_encode(["debit"])
            ],
            [
                "name" => "Troy",
                "code" => "troy",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "Multibanco",
                "code" => "multibanco",
                "type" => json_encode(["debit"])
            ],
            [
                "name" => "Elo",
                "code" => "elo",
                "type" => json_encode(["credit", "debit"])
            ],
            [
                "name" => "Cabal",
                "code" => "cabal",
                "type" => json_encode(["credit", "debit"])
            ]
        ];

        CardBrand::insert($brands);
    }
}
