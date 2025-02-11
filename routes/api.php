<?php

use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

            //sales
            Route::group([
                'prefix' => 'sales',
                "namespace" => "Sales"
            ], function () {

                //invoices
                Route::group([
                    "namespace" => "SalesInvoice"
                ], function () {
                    Route::apiResource('invoices', 'InvoiceController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('/invoices/create/generateId', 'InvoiceController@generateInvoiceId');
                    Route::post('/invoices/{id}/record-payment', 'InvoiceController@recordPayment');
                    Route::post('/invoices/{invoice}/write-off', 'InvoiceController@writeOffInvoice');
                    Route::put('/invoices/{invoice}/void', 'InvoiceController@voidInvoice');
                });

                //quotes
                Route::group([
                    "namespace" => "SalesQuote"
                ], function () {
                    Route::apiResource('quotes', 'QuoteController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('/quotes/create/generateId', 'QuoteController@generateQuoteId');
                });

                //customers
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
                    Route::get('{id}/generate-statement', 'CustomerController@generateCustomerStatement');
                });

                //credit notes
                Route::group([
                    "namespace" => "CreditNote"
                ], function () {
                    Route::apiResource('credit-notes', 'CreditNoteController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                });
            });

            Route::group([
                'prefix' => 'purchases',
                "namespace" => "Purchase"
            ], function () {

                //suppliers
                Route::group([
                    "namespace" => "Vendor"
                ], function () {
                    Route::apiResource('suppliers', 'VendorController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::group([
                        "prefix" => "suppliers",
                    ], function () {
                        Route::post('/create-individual', 'VendorController@createIndividualSupplier');
                        Route::post('/create-organization', 'VendorController@createOrganizationSupplier');
                        Route::put('/{id}/update-individual', 'VendorController@updateIndividualSupplier');
                        Route::put('/{id}/update-organization', 'VendorController@updateOrganizationSupplier');
                        Route::patch('/change-status/{id}', 'VendorController@changeStatus');
                        Route::delete('/delete{id}', 'VendorController@delete');
                        Route::get('/generate/reference', 'VendorController@generateReference');
                    });
                });

                //purchase orders
                Route::group([
                    "namespace" => "PurchaseOrder"
                ], function () {
                    Route::apiResource('orders', 'OrderController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('/orders/generate/purchase-no', 'OrderController@generateOrderNumber');
                    Route::get('/orders/{id}/line-items', 'OrderController@purchaseOrderLineItems');
                });

                //purchase invoice
                Route::group([
                    "namespace" => "PurchaseInvoice"
                ], function () {
                    Route::apiResource('invoices', 'InvoiceController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('/invoices/generate/invoiceID', 'InvoiceController@generateInvoiceNumber');
                    Route::get('/invoices/{id}/purchase-order/{purchase_order}', 'InvoiceController@matchPurchaseOrder');
                    Route::get('/invoices/{id}/line-items', 'InvoiceController@purchaseInvoiceLineItems');
                });

                //Record payment
                Route::group([
                    "namespace" => "RecordPayment"
                ], function () {
                    Route::apiResource('record-payments', 'RecordPaymentController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                });

                //Bills
                Route::group([
                    "namespace" => "Bills"
                ], function () {
                    Route::apiResource('bills', 'BillsController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('/bills/generate/billID', 'BillsController@generateReference');
                    Route::get('/bills/{id}/void', 'BillsController@voidBill');
                    Route::post('/bills/{id}/recurring', 'BillsController@recurringBill');
                });
            });

            Route::group([
                'prefix' => 'banking',
                "namespace" => "Banking"
            ], function () {

                //Payment methods
                Route::group([
                    "namespace" => "PaymentMethods"
                ], function () {
                    //cards
                    Route::apiResource('cards', 'CardController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::group([
                        "prefix" => "cards",
                    ], function () {
                        Route::get('/transactions/all', 'CardController@cardTransactions');
                        Route::put('/{id}/toggle-status', 'CardController@toggleStatus');
                        Route::get('/card-by-bin/{bin}', 'CardController@cardByBin');
                    });
                    //banks
                    Route::apiResource('bank-accounts', 'BankAccountController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::group([
                        "prefix" => "bank-accounts",
                    ], function () {
                        Route::get('/transactions/all', 'BankAccountController@bankTransactions');
                        Route::put('/{id}/toggle-status', 'BankAccountController@toggleStatus');
                        Route::get('/types/all', 'BankAccountController@accountTypes');
                        Route::get('/connection/initiate', 'BankAccountController@initiateConnection');
                        Route::post('/connection/complete', 'BankAccountController@completeConnection');
                    });
                });
            });

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
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::put('users/toggle-status/{user}', 'UsersController@toggleStatus');
                });
                Route::group([
                    "namespace" => "ManageRoles"
                ], function () {
                    Route::apiResource('roles', 'RolesController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::get('roles/{role}/permissions', 'RolesController@permissions');
                    Route::put('roles/toggle-status/{role}', 'RolesController@toggleStatus');
                });
            });

            Route::group([
                'prefix' => 'settings',
                "namespace" => "Settings"
            ], function () {

                Route::group([
                    'prefix' => 'organization',
                    "namespace" => "Organization"
                ], function () {
                    Route::put('update', 'OrganizationDetailsController@update');
                    Route::get('view', 'OrganizationDetailsController@view');
                });
                Route::group([
                    "namespace" => "EmailSettings"
                ], function () {
                    Route::apiResource('email', 'EmailSettingsController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                });
                Route::group([
                    'prefix' => 'reset-password',
                    "namespace" => "ResetPassword"
                ], function () {
                    Route::post('validate-password', 'ResetPasswordController@validateCurrentPassword');
                    Route::post('confirm-otp', 'ResetPasswordController@confirmOtp');
                    Route::get('resend-otp', 'ResetPasswordController@resendOtp');
                    Route::put('update-password', 'ResetPasswordController@createNewPassword');
                });
                Route::group([
                    'prefix' => 'subscriptions',
                    "namespace" => "Subscriptions"
                ], function () {});
                Route::group([
                    'prefix' => 'document',
                    "namespace" => "DocumentSettings"
                ], function () {
                    Route::group([
                        'prefix' => 'basic',
                    ], function () {
                        Route::get('/', 'BasicSettingsController@view');
                        Route::patch('/modify', 'BasicSettingsController@modify');
                    });
                    Route::group([
                        'prefix' => 'default',
                    ], function () {
                        Route::get('/', 'DefaultSettingsController@view');
                        Route::patch('/modify', 'DefaultSettingsController@modify');
                    });
                    Route::group([
                        'prefix' => 'default',
                    ], function () {
                        Route::get('/', 'DefaultSettingsController@view');
                        Route::patch('/modify', 'DefaultSettingsController@modify');
                    });
                    Route::group([
                        'prefix' => 'invoice-remainder',
                    ], function () {
                        Route::get('/', 'InvoiceRemainderController@index');
                        Route::get('/view/{id}', 'InvoiceRemainderController@view');
                        Route::patch('/modify', 'InvoiceRemainderController@modify');
                    });
                });
                Route::group([
                    "namespace" => "TaxRate"
                ], function () {
                    Route::apiResource('taxes', 'TaxRateController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                });
            });

            Route::group([
                "namespace" => "SharedActions"
            ], function () {
                Route::match(['get', 'post', 'put', 'delete'], '/shared/{prefix}/{model}/{id}/{action}', 'SharedActionController');
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
        Route::get('/card-brands', 'GuestController@cardBrands');
        Route::get('/all-banks', 'GuestController@allBanks');
        Route::get('/all-email-templates', 'GuestController@allEmailTemplate');
        Route::get('/fonts', 'GuestController@fonts');
    });
});
