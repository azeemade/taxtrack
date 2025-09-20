<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyHouseCompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company_name' => $this['title'],
            'company_number' => $this['company_number'],
            'address' => $this['address_snippet'],
            'company_status' => $this['company_status'],
            'date_of_creation' => $this['date_of_creation']
        ];
    }
}
