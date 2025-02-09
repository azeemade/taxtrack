<?php

namespace App\Services\ThirdPartyApi;

use Illuminate\Support\Facades\Http;

class FontServiceApi
{
    /**
     * Get fonts
     */

    public static function getFonts()
    {
        $url = config('thirdpartyapi.font_list');

        return $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->get($url);
        if (!$response->successful()) {
            return false;
        }
        return $response;//->json();
    }
}
