<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    // public function toArray(Request $request): array
    // {
    //     return parent::toArray($request);
    // }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->companyUUID,
            'name' => $this->name,
            'email' => $this->email,
            'contact_person' => $this->companyAdmin->name,
            'staff_count' => $this->staff->count(),
            'status' => $this->status,
            'current_plan' => $this->subscriber->subscriptionPlan->title ?? null,
            'subscription_status' => $this->subscriber->currentSubscriptionHistory->status ?? null,
            'date_created' => Carbon::parse($this->created_at)->toFormattedDayDateString(),
        ];
    }
}
