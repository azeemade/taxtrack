<?php

return [
    'card_by_bin' => env('CARD_BY_BIN', 'https://data.handyapi.com/bin/'),
    'font_list' => 'https://api.fontsource.org/fontlist?family',
    'company_house' => [
        'base_url' => env('COMPANY_HOUSE_BASE_URL', 'https://api.company-information.service.gov.uk'),
        'key' => env('COMPANY_HOUSE_KEY', ''),
        'endpoints' => [
            'search' => '/search/companies',
        ],
    ]
];
