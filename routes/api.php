<?php

use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'v1',
    "namespace" => 'App\Http\Controllers\v1'
], function () {
    Route::group([
        'prefix' => 'auth',
        "namespace" => "Auth"
    ], function () {
        Route::post('/login', 'AuthController@login');
        Route::post('/signup', 'AuthController@signup');
        Route::get('/switch-company/{id}', 'AuthController@switchCompany')->middleware('auth:api');
        Route::get('/logout', 'AuthController@logout')->middleware('auth:api');
        Route::post('/refresh', 'AuthController@refresh')->middleware('auth:api');
        Route::group([
            'prefix' => 'onboarding',
            "namespace" => "Onboarding"
        ], function () {
            Route::post('/user-check', 'RegisterController@userCheck');
            Route::post('/basic-information', 'RegisterController@basicInformation');
            Route::post('/verify-token', 'RegisterController@verifyToken');
            Route::post('/resend-token', 'RegisterController@resendToken');
            Route::post('/add-company/{id}', 'RegisterController@addCompany');
            Route::post('/add-role/{id}', 'RegisterController@addRole');
            Route::post('/invite-users/{id}', 'RegisterController@inviteUsers');
            Route::post('/complete/{id}', 'RegisterController@completeOnboarding');
        });
        Route::group([
            "namespace" => "ResetPassword"
        ], function () {
            Route::post('/send-reset-email', 'ResetPasswordController@sendResetLink');
            Route::put('/reset-password', 'ResetPasswordController@resetPassword');
        });
    });
    Route::group(['middleware' => ["auth:api"]], function () {
        Route::group([
            'prefix' => 'client',
            'middleware' => ['permission:access_client_app,api'],
            "namespace" => "Company"
        ], function () {
            Route::group([
                'prefix' => 'dashboard',
            ], function () {});

            Route::group([
                'prefix' => 'sales',
                "namespace" => "Sales"
            ], function () {
                Route::group([
                    "namespace" => "SalesInvoice"
                ], function () {
                    Route::apiResource('invoices', 'InvoiceController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, 404);
                        });
                });
                Route::group([
                    "namespace" => "SalesQuote"
                ], function () {
                    Route::apiResource('quotes', 'QuoteController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, 404);
                        });
                });
                Route::group([
                    "prefix" => "customers",
                    "namespace" => "Customer"
                ], function () {
                    Route::get('', 'CustomerController@overview');
                    Route::get('/{id}', 'CustomerController@view');
                    Route::post('/create-individual', 'CustomerController@createIndividualCustomer');
                    Route::post('/create-organization', 'CustomerController@createOrganizationCustomer');
                    Route::put('/{id}/update-individual', 'CustomerController@updateIndividualCustomer');
                    Route::put('/{id}/update-organization', 'CustomerController@updateOrganizationCustomer');
                    Route::patch('/change-status/{id}', 'CustomerController@changeStatus');
                    Route::delete('/delete{id}', 'CustomerController@delete');
                });
                Route::group([
                    "namespace" => "CreditNote"
                ], function () {
                    Route::apiResource('credit-notes', 'CreditNoteController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, 404);
                        });
                });
            });

            Route::group([
                'prefix' => 'purchases',
                "namespace" => "Purchase"
            ], function () {});

            Route::group([
                'prefix' => 'banking',
                "namespace" => "Banking"
            ], function () {});

            Route::group([
                'prefix' => 'accounting',
                "namespace" => "Accounting"
            ], function () {});

            Route::group([
                'prefix' => 'tools',
                "namespace" => "Tools"
            ], function () {});

            Route::group([
                'prefix' => 'budgets',
                "namespace" => "Budget"
            ], function () {});

            Route::group([
                'prefix' => 'reports',
                "namespace" => "Report"
            ], function () {});

            Route::group([
                'prefix' => 'user-management',
                "namespace" => "UserManagement"
            ], function () {
                Route::group([
                    "namespace" => "ManageUsers"
                ], function () {
                    Route::apiResource('users', 'UsersController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, 404);
                        });
                    Route::put('users/toggle-status/{user}', 'UsersController@toggleStatus');
                });
                Route::group([
                    "namespace" => "ManageRoles"
                ], function () {
                    Route::apiResource('roles', 'RolesController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, 404);
                        });
                    Route::get('roles/{role}/permissions', 'RolesController@permissions');
                    Route::put('roles/toggle-status/{role}', 'RolesController@toggleStatus');
                });
            });
            Route::group([
                'prefix' => 'shared',
                "namespace" => "SharedActions"
            ], function () {
                Route::post('{prefix}/{model}/{id}/{action}', 'SharedActionController');
            });
        });
    });
    Route::group([
        'prefix' => 'guests',
        "namespace" => "Guest"
    ], function () {
        Route::get('/categories', 'GuestController@categories');
        Route::get('/industries', 'GuestController@industries');
        Route::get('/permissions', 'GuestController@allPermissions');
        Route::get('/user/{id}/permissions', 'GuestController@getUserPermissions');
        Route::get('/user/{id}/companies', 'GuestController@getUserCompanies');
        Route::get('/company/{id}/roles', 'GuestController@getCompanyRoles');
        Route::get('/error-logs', 'GuestController@errorLogs');
    });
});
