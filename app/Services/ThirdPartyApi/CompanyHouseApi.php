<?php

namespace App\Services\ThirdPartyApi;

use App\Exceptions\BadRequestException;
use Illuminate\Support\Facades\Http;

class CompanyHouseApi
{
    /**
     * Search companies
     */

    public static function companySearch($query, $items_per_page = 10, $start_index = 0)
    {
        $url = config('thirdpartyapi.company_house.base_url') . config('thirdpartyapi.company_house.endpoints.search');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode(config('thirdpartyapi.company_house.key')),
        ])->get($url, [
            'q' => $query,
            'items_per_page' => $items_per_page,
            'start_index' => $start_index,
        ]);
        if (!$response->successful()) {
            throw new BadRequestException($response->json()['errors'][0]['error'] ?? 'Company search failed');
        }
        return $response->json();
    }
}
