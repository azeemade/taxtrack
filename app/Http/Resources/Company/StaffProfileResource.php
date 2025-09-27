<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uei_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'last_login' => $this->last_login,
            'company_type' => $this->company_type,
            'onboarding_completed' => $this->onboarding_completed,
            'status' => $this->status,
            'can_login' => $this->can_login,
            'subscription' => $this->getCurrentSubscriptionDetails()->only('id', 'subscribed_at', 'end_date', 'subscription_plan_id', 'plan_amount', 'amount_paid'),
            'user_permissions' => $this->user_permissions->map(function ($permission) {
                return $permission->only('id', 'name', 'app', 'module', 'submodule');
            }),
            'user_permissions_count' => $this->user_permissions_count,
            'roles' => $this->roles->map(function ($role) {
                return $role->only('id', 'name', 'status', 'slug', 'roleID');
            }),

            'company' => array_merge(
                $this->company->toArray(),
                [
                    "tax_type" => $this->company->tax_type === 'standard' || !$this->company->tax_type ? config('system.standard_tax_rate_schema') : $this->company->tax_type,
                    'current_currency' => $this->company->currentCurrency()
                ]
            ),
        ];
    }
}
