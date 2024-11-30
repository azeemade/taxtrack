<?php

use Illuminate\Support\Facades\Route;

// Route::group(["middleware" => "auth:api"], function () {
//     Route::group([
//         'prefix' => 'admin',
//         'middleware' => ['permission:access_admin_app,api'],
//         "namespace" => "Admin"
//     ], function () {
Route::group([
    'prefix' => 'dashboard',
    "namespace" => "Dashboard"
], function () {});
Route::group([
    'prefix' => 'company',
    "namespace" => "Company"
], function () {
    Route::get('/', 'CompanyManagementController@overview');
    Route::post('/create', 'CompanyManagementController@create');
    Route::post('/attach-user', 'CompanyManagementController@attachUser');
});
Route::group([
    'prefix' => 'subscriptions',
    "namespace" => "Subscription"
], function () {
    Route::group([
        'prefix' => 'overview'
    ], function () {});
    Route::group([
        'prefix' => 'manage-subscription'
    ], function () {});
    Route::group([
        'prefix' => 'manage-subscriber'
    ], function () {});
});
Route::group([
    'prefix' => 'user-management',
    "namespace" => "UserManagement"
], function () {});
//     });
// });
