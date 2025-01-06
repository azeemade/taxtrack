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
        Route::apiResource('plan', 'ManageSubscriptionController')->missing(function () {
            return JsonResponser::send(true, 'Resource not found', null, 404);
        });
        Route::post('create/history', 'ManageSubscriptionController@createHistory');
        Route::get('show/subscribers/{id}', 'ManageSubscriptionController@showSubscriberSubscription');
        Route::get('view/receipts/{id}', 'ManageSubscriptionController@viewReceipts');
        Route::put('approve/refund/{id}', 'ManageSubscriptionController@approveRefund');
        Route::put('module/toggle-status/{id}', 'ManageSubscriptionController@toggleStatus');
        Route::get('module/functionalities', 'ManageSubscriptionController@moduleFunctionalities');
    });

    Route::group(['prefix' => 'manage-subscriber'], function () {
        Route::apiResource('subscriber', 'ManageSubscribersController')->missing(function () {
            return JsonResponser::send(true, 'Resource not found', null, 404);
        });
        Route::put('approve/refund/{id}', 'ManageSubscriptionController@approveRefund');
        Route::get('view/receipts/{id}', 'ManageSubscriptionController@viewReceipts');
    });
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
        Route::post('users/roles', 'UserManagementController@roles');
    });
});
//     });
// });
