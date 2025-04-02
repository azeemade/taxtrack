<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'monthly_fee' => $this->monthly_fee,
            'yearly_fee' => $this->yearly_fee,
            'short_description' => $this->short_description,
            'is_active' => $this->is_active,
            'duration' => $this->duration,
            'default_seat' => $this->default_seat,
            'status' => $this->status,
            'features' => $this->subscriptionPlanFeature->pluck('title'),
            'modules' => $this->getModules($this)
        ];
    }

    protected function getModules($plan)
    {
        $subscriptionFunctionalities = $plan->subscriptionFunctionality;
        $moduleIds = array_unique($subscriptionFunctionalities->pluck('module_id')->toArray());
        $modules = [];
        foreach ($moduleIds as $moduleId) {
            $modules[] = [
                "module_id" => $moduleId,
                "module_functionality_id" => $subscriptionFunctionalities->where('module_id', $moduleId)->pluck('module_functionality_id')
            ];
        }
        return $modules;
    }
}
