<?php

namespace App\Services\ThirdPartyApi;

use Illuminate\Support\Facades\Http;

class CardServiceApi
{
    /**
     * Get card data by BIN
     */
    public static function cardByBin($bin)
    {
        $url = config('thirdpartyapi.card_by_bin') . $bin;

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->get($url);
        if (!$response->successful()) {
            return false;
        }
        return $response->json();
    }
}
