<?php

use App\Responser\JsonResponser;
use Illuminate\Support\Facades\Route;

// Route::group(["middleware" => "auth:api"], function () {
//     Route::group([
//         'prefix' => 'admin',
//         'middleware' => ['permission:access_admin_app,api'],
//         "namespace" => "Admin"
//     ], function () {

Route::group(['prefix' => 'dashboard', "namespace" => "Dashboard"], function () {});

Route::group(['prefix' => 'company', "namespace" => "Company"], function () {
    Route::get('/', 'CompanyManagementController@overview');
    Route::post('/create', 'CompanyManagementController@create');
    Route::post('/attach-user', 'CompanyManagementController@attachUser');
});

Route::group(['prefix' => 'subscriptions', "namespace" => "Subscription"], function () {

    Route::group(['prefix' => 'overview'], function () {});

    Route::group(['prefix' => 'manage-subscription'], function () {
        Route::post('/create-plan', 'ManageSubscriptionController@store');
        Route::get('/{id}', 'ManageSubscriptionController@show');
    });

    Route::group(['prefix' => 'manage-subscriber'], function () {});
});

Route::group(['prefix' => 'user-management', "namespace" => "UserManagement"], function () {

    Route::group(["namespace" => "Roles"], function () {
        Route::apiResource('roles', 'RoleManagementController')->missing(function () {
            return JsonResponser::send(true, 'Resource not found', null, 404);
        });
        Route::post('roles/permissions', 'RoleManagementController@permissions');
        Route::put('roles/toggle-status/{role}', 'RoleManagementController@toggleStatus');
    });

    Route::group(["namespace" => "Users"], function () {
        Route::apiResource('users', 'UserManagementController')->missing(function () {
            return JsonResponser::send(true, 'Resource not found', null, 404);
        });
        Route::put('users/toggle-status/{user}', 'UserManagementController@toggleStatus');
    });
});
//     });
// });
