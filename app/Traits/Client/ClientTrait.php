<?php

namespace App\Traits\Client;

trait ClientTrait
{
    public function getCurrentSubscriptionDetails()
    {
        $currentSubscription = $this->company?->subscriber?->currentSubscriptionHistory; //->only('id', 'subscribed_at', 'end_date', 'subscription_plan_id', 'team_size');
        // dd($currentSubscription);
        return $currentSubscription?->load(
            [
                'plan:id,title' => [
                    'subscriptionFunctionality:id,module_functionality_id,subscription_plan_id' => [
                        'moduleFunctionality:id,name,module_id' => [
                            'module:id,name'
                        ]
                    ]
                ]
            ]
        ) ?? null;
    }
}
