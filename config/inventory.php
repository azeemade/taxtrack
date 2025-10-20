<?php

return [
    // category slugs that should post COGS/Inventory
    'inventory_slugs' => ['hardware', 'materials', 'equipment', 'inventory', 'stock'],

    // Category → expense account slug mapping
    'category_account_map' => [
        'consulting'          => 'professional-fees',
        'labor'               => 'staff-costs',
        'maintenance'         => 'repairs-and-maintenance',
        'installation'        => 'professional-fees',
        'project-management'  => 'professional-fees',
        'design'              => 'professional-fees',
        'utilities'           => 'utilities',
        'delivery'            => 'freight-costs',
        'development'         => 'software-development-expense',
        'travel'              => 'travel-and-accommodation',
        'training'            => 'staff-training',
        'marketing'           => 'marketing-advertising',
        'subscription'        => 'software-subscriptions',
        'licensing'           => 'software-licensing',
        'software'            => 'software-purchases',
        'miscellaneous'       => 'general-expenses',
        'testing'             => 'general-expenses',
    ],

    // Fallback when a category slug is not mapped and not inventory
    'fallback_expense_slug' => 'office-cost',  // or 'office-cost' if you prefer
];
