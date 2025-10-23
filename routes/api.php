<?php

use App\Http\Controllers\v1\Company\Accounting\ChartOfAccount\ChartOfAccountController;
use App\Http\Controllers\v1\Company\Accounting\JournalOfEntry\JournalOfEntryController;
use App\Http\Controllers\v1\Company\Banking\AccountReconciliation\AccountReconciliationController;
use App\Http\Controllers\v1\Company\Banking\PaymentMethods\BankAccountControllerRework;
use App\Http\Controllers\v1\Company\Banking\Transactions\TransactionsController;
use App\Http\Controllers\v1\Company\Report\BudgetVariance\BudgetVarianceController;
use App\Http\Controllers\v1\Company\Report\FinancialPerformance\BusinessPerformanceController;
use App\Http\Controllers\v1\Company\Report\FinancialPerformance\BusinessSnapshotController;
use App\Http\Controllers\v1\Company\Report\CashSummary\CashSummaryController;
use App\Http\Controllers\v1\Company\Report\FinancialStatement\FinancialStatementController;
use App\Http\Controllers\v1\Company\Report\Reconciliation\ReconciliationController;
use App\Http\Controllers\v1\Company\Report\TaxAndBalances\TaxBalancesController;
use App\Http\Controllers\v1\Company\Report\Transaction\AccountTransactionController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\v1\Company\Report\VAT\VATReturnController;
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
            Route::get('/company-check', 'RegisterController@companyCheck');
            Route::post('/verify-token', 'RegisterController@verifyToken');
            Route::post('/resend-token/{id}', 'RegisterController@resendToken');
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
    Route::group(
        ['middleware' => ["auth:api"]],
        function () {
            Route::group([
                'prefix' => 'client',
                'middleware' => ['permission:access_client_app,api'],
                "namespace" => "Company"
            ], function () {
                Route::group([
                    'prefix' => 'dashboard',
                    'namespace' => 'Dashboard'
                ], function () {
                    Route::get('/cash-flow', 'DashboardController@cashFlow');
                    Route::get('/total-receivables', 'DashboardController@totalReceivables');
                    Route::get('/total-payables', 'DashboardController@totalPayables');
                    Route::get('/income-expenses/bar-chart', 'DashboardController@incomeAndExpenseBarChart');
                    Route::get('/income-expenses/pie-chart', 'DashboardController@incomeAndExpensePieChart');
                });
                Route::group([
                    'prefix' => 'audit-trails',
                    'namespace' => 'AuditLog'
                ], function () {
                    Route::get('/', 'AuditLogController@index');
                });

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
                        Route::post('/quotes/{id}/convert-to-invoice', 'QuoteController@convertToInvoice');
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
                        Route::group([
                            "prefix" => "analytics"
                        ], function () {
                            Route::get('/payment-duration', 'CustomerAnalyticsController@paymentDuration');
                            Route::get('/payment-consistency', 'CustomerAnalyticsController@paymentConsistency');
                            Route::get('/outstanding-balance', 'CustomerAnalyticsController@outstandingBalance');
                        });
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
                        Route::get('/orders/not-converted/shared', 'OrderController@ordersNotConvertedToInvoice');
                    });

                    //purchase invoice
                    Route::group([
                        "namespace" => "PurchaseInvoice"
                    ], function () {
                        Route::apiResource('invoices', 'InvoiceController')
                            ->names([
                                'index' => 'purchase_invoices.index',
                                'store' => 'purchase_invoices.store',
                                'show' => 'purchase_invoices.show',
                                'update' => 'purchase_invoices.update',
                                'destroy' => 'purchase_invoices.destroy',
                            ])
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
                        Route::get('record-payments/all/payment-methods/{method?}', 'RecordPaymentController@allPaymentMethods');
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

                    //Debit notes
                    Route::group([
                        "namespace" => "DebitNote"
                    ], function () {
                        Route::apiResource('debit-notes', 'DebitNoteController')
                            ->missing(function () {
                                return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                            });
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

                    //transactions
                    Route::group([
                        "namespace" => "Transactions",
                        "prefix" => "transactions",
                    ], function () {
                        Route::put('/{id}/payment-methods', 'TransactionsController@updatePaymentMethodTransaction');
                    });
                });


                Route::group(['prefix' => 'banking-rw', "namespace" => "Banking"], function () {
                    Route::prefix('bank-accounts')->group(function () {
                        Route::post('/', [BankAccountControllerRework::class, 'index']);
                        Route::get('/stats', [BankAccountControllerRework::class, 'stats']);
                        Route::post('/card-view', [BankAccountControllerRework::class, 'indexCardView']);
                        Route::post('/store', [BankAccountControllerRework::class, 'store']);
                        Route::get('/{id}', [BankAccountControllerRework::class, 'show']);
                        Route::put('/update/{id}', [BankAccountControllerRework::class, 'update']);
                        Route::delete('/delete/{id}', [BankAccountControllerRework::class, 'delete']);
                        Route::put('/toggle/{id}', [BankAccountControllerRework::class, 'toggleStatus']);
                    });

                    Route::prefix('transactions')->group(function () {
                        // Route::get('/stats', [TransactionsController::class, 'dashboardStats']);
                        // Route::get('/groups_not_paginated', [TransactionsController::class, 'allFinanceTransactionGroupsNotPaginated']);
                        Route::post('/all_groups', [TransactionsController::class, 'allFinanceTransactionGroups']);
                        // Route::post('/all', [TransactionsController::class, 'allFinanceTransactions']);
                        Route::post('/overview', [TransactionsController::class, 'transactionOverview']);
                        Route::post('/list', [TransactionsController::class, 'transactionList']);
                        Route::post('/create', [TransactionsController::class, 'createFinanceTransaction']);
                        Route::post('/update/{id}', [TransactionsController::class, 'updateFinanceTransaction']);
                        Route::get('/show/{id}', [TransactionsController::class, 'viewFinanceTransactionGroup']);
                        // Route::post('/create_payment', [TransactionsController::class, 'createPaymentFinanceTransaction']);
                        // Route::post('/create_receipt', [TransactionsController::class, 'createReceiptFinanceTransaction']);
                        // Route::put('/update/{id}', [TransactionsController::class, 'updateFinanceTransactionGroup']);
                        // Route::delete('/delete/{id}', [TransactionsController::class, 'deleteFinanceTransactionGroup']);
                        // Route::delete('/single/delete/{id}', [TransactionsController::class, 'deleteSingleFinanceTransaction']);
                    });

                    Route::prefix('reconciliation')->group(function () {
                        Route::post('/upload-bankstatement', [ReconciliationController::class, 'uploadBankStatement']);
                        Route::get('/download-bankstatement-template', [ReconciliationController::class, 'downloadBankStatementTemplate']);
                        Route::post('/build-and-save-reconciliation-records', [ReconciliationController::class, 'buildAndSaveReconciliationRecords']);
                        Route::post('/list-reconciliation-runs', [ReconciliationController::class, 'listReconciliationRuns']);
                        Route::get('/get-reconciliation-run/{runId}', [ReconciliationController::class, 'getReconciliationRun']);
                        Route::get('/all_statements', [ReconciliationController::class, 'allBankStatements']);
                        // Route::get('/all_account_transactions', [ReconciliationController::class, 'allAccountTransactions']);

                        // Route::post('/bank-reconciliation-summary', [ReconciliationController::class, 'getBankReconciliationSummary']);
                        // Route::post('/bank-reconciliation-lines', [ReconciliationController::class, 'getBankReconciliationLines']);
                        // Route::post('/reconcile-lines', [ReconciliationController::class, 'reconcileLine']);


                        // Route::post('/list-reconciliation-records', [ReconciliationController::class, 'listReconciliationRecords']);
                        // Route::post('/get-reconciliation-summary-from-records', [ReconciliationController::class, 'getReconciliationSummaryFromRecords']);
                    });
                });


                Route::group(['prefix' => 'accounting', "namespace" => "Accounting"], function () {
                    Route::prefix('chart-of-accounts')->group(function () {
                        Route::post('/', [ChartOfAccountController::class, 'index']);
                        Route::post('/store', [ChartOfAccountController::class, 'createAccount']);
                        Route::get('/{id}', [ChartOfAccountController::class, 'show']);
                        Route::put('/update/{id}', [ChartOfAccountController::class, 'updateAccount']);
                        Route::delete('/delete/{id}', [ChartOfAccountController::class, 'delete']);
                        Route::put('/toggle/{id}', [ChartOfAccountController::class, 'toggleStatus']);
                        Route::get('/sublist/all-accounts/notpaginated', [ChartOfAccountController::class, 'allChartOfAccountNotPaginated']);
                        Route::get('/sublist/subcategories/notpaginated', [ChartOfAccountController::class, 'allSubCategoriesNotPaginated']);
                        Route::get('/sublist/account/{accountType}', [ChartOfAccountController::class, 'getAccountsByType']);
                        Route::get('/sublist/account_by_subcategory_id/{id}', [ChartOfAccountController::class, 'accountBySubCategoryID']);
                        Route::get('/sublist/account_by_subcategory/{accountSubCategory}', [ChartOfAccountController::class, 'accountSubCategoryName']);
                        Route::get('/download/template', [ChartOfAccountController::class, 'getDownload']);
                        Route::post('/import', [ChartOfAccountController::class, 'importAccount']);
                        Route::post('/load-default-accounts', [ChartOfAccountController::class, 'loadDefaultAccounts']);
                    });

                    Route::prefix('journal-entry')->group(function () {
                        Route::post('/', [JournalOfEntryController::class, 'index']);
                        Route::post('/store', [JournalOfEntryController::class, 'createJournalEntry']);
                        Route::get('/{id}', [JournalOfEntryController::class, 'show']);
                        Route::put('/update/{id}', [JournalOfEntryController::class, 'updateJournalEntry']);
                        Route::delete('/delete/{id}', [JournalOfEntryController::class, 'delete']);
                    });
                });

                Route::group([
                    'prefix' => 'tools',
                    "namespace" => "Tools"
                ], function () {});

                Route::group([
                    "namespace" => "Budget"
                ], function () {
                    Route::apiResource('budgets', 'BudgetController')
                        ->missing(function () {
                            return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                        });
                    Route::put('budgets/{id}/toggle-status', 'BudgetController@toggleStatus');
                    Route::get('budgets/compute/periods', 'BudgetController@computePeriods');
                    Route::get('budgets/create/template', 'BudgetController@getBudgetTemplate');
                });

                Route::group(['prefix' => 'report', "namespace" => "Report"], function () {
                    Route::group(['prefix' => 'financial-performance'], function () {
                        Route::group(['prefix' => 'business-snapshot'], function () {
                            Route::post('/profit-or-loss', [BusinessSnapshotController::class, 'getProfitAndLossReport']);
                            Route::post('/income', [BusinessSnapshotController::class, 'getIncomeReport']);
                            Route::post('/expense', [BusinessSnapshotController::class, 'getExpensesReport']);
                            Route::post('/net-profit-margin', [BusinessSnapshotController::class, 'getNetProfitMarginReport']);
                            Route::post('/balance-sheet', [BusinessSnapshotController::class, 'getBalanceSheetReport']);
                            Route::post('/cash-balance', [BusinessSnapshotController::class, 'getCashBalancesReport']);
                            Route::post('/operating-expenses', [BusinessSnapshotController::class, 'getOperatingExpensesReport']);
                            Route::post('/average-time', [BusinessSnapshotController::class, 'getAverageTime']);
                            Route::post('/business-snapshot-summary', [BusinessSnapshotController::class, 'businessSnapshotSummary']);
                        });

                        Route::group(['prefix' => 'business-performance', "namespace" => "FinancialPerformance"], function () {
                            Route::post('/liability-to-net-worth', [BusinessPerformanceController::class, 'getLiabilityToNetWorthRatio']);
                            Route::post('/debt-to-equity', [BusinessPerformanceController::class, 'getDebtToEquityRatio']);
                            Route::post('/fixed-asset-to-net-worth', [BusinessPerformanceController::class, 'getFixedAssetToNetWorthRatio']);
                            Route::post('/gross-profit', [BusinessPerformanceController::class, 'getGrossProfitPercentage']);
                            Route::post('/net-profit-to-net-sales', [BusinessPerformanceController::class, 'getNetProfitOnNetSales']);
                            Route::post('/working-capital-to-asset', [BusinessPerformanceController::class, 'getWorkingCapitalToTotalAssets']);
                            Route::post('/business-performance-summary', [BusinessPerformanceController::class, 'getBusinessSnapshotSummary']);
                        });

                        Route::group(['prefix' => 'cash-summary', "namespace" => "CashSummary"], function () {
                            Route::post('/', [CashSummaryController::class, 'index']);
                        });

                        Route::group(['prefix' => 'budget-variance', "namespace" => "BudgetVariance"], function () {
                            Route::get('/', [BudgetVarianceController::class, 'index']);
                        });
                    });

                    Route::group(['prefix' => 'financial-statement'], function () {
                        Route::post('/balance-sheet', [FinancialStatementController::class, 'getBalanceSheetReport']);
                        Route::post('/balance-sheet-run-at-date', [FinancialStatementController::class, 'getBalanceSheetMajorRunAtDate']);
                        Route::post('/profit-or-loss', [FinancialStatementController::class, 'profitAndLossGroupbyCategory']);
                    });

                    Route::group(['prefix' => 'reconciliation'], function () {
                        Route::post('/account-summary', [ReconciliationController::class, 'accountSummary']);

                        Route::post('/bank-summary', [CashSummaryController::class, 'index']);
                        Route::post('/trial-balance', [ReconciliationController::class, 'trialBalance']);

                        Route::post('/bank-reconciliation-summary', [ReconciliationController::class, 'bankReeconciliationSummary']);
                    });

                    Route::group(['prefix' => 'tax-and-balances'], function () {
                        Route::post('/sales-tax-report', [TaxBalancesController::class, 'salesTaxReport']);
                        Route::post('/journal-report', [TaxBalancesController::class, 'journalReport']);
                        Route::post('/foreign-currency-gain-and-losses', [TaxBalancesController::class, 'foreignCurrencyGainAndLosses']);
                        Route::post('/general-ledger-details', [TaxBalancesController::class, 'generalLedgerDetails']);
                        Route::post('/general-ledger-summary', [TaxBalancesController::class, 'generalLedgerSummary']);
                        Route::post('/vat-return-flat-rate', [VATReturnController::class, 'generateFlatRateVatReturn']);
                        Route::post('/vat-return-standard-rate', [VATReturnController::class, 'generateStandardRateVatReturn']);
                        Route::post('/vat-return', [VATReturnController::class, 'generateVatReturn']);
                    });

                    Route::group(['prefix' => 'transaction'], function () {
                        Route::post('/account-transactions', [AccountTransactionController::class, 'index']);
                    });

                    Route::group(['prefix' => 'payables-receivables'], function () {
                        Route::get('/aged-payable-details', 'PayablesAndReceivablesController@agedPayableDetails');
                        Route::get('/aged-payable-summary', 'PayablesAndReceivablesController@agedPayableSummary');
                        Route::get('/aged-receivable-details', 'PayablesAndReceivablesController@agedReceivableDetails');
                        Route::get('/aged-receivable-summary', 'PayablesAndReceivablesController@agedReceivableSummary');
                        Route::get('/payable-invoice-details', 'PayablesAndReceivablesController@payableInvoiceDetails');
                        Route::get('/payable-invoice-summary', 'PayablesAndReceivablesController@payableInvoiceSummary');
                        Route::get('/receivable-invoice-details', 'PayablesAndReceivablesController@receivableInvoiceDetails');
                        Route::get('/receivable-invoice-summary', 'PayablesAndReceivablesController@receivableInvoiceSummary');
                    });
                });

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
                        Route::post('add-company', 'OrganizationDetailsController@addCompany');
                        Route::get('list-companies', 'OrganizationDetailsController@listCompanies');
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
                        "namespace" => "Subscription"
                    ], function () {
                        Route::apiResource('cancellations', 'SubscriptionCancellationController')
                            ->missing(function () {
                                return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                            });
                        Route::apiResource('refund-requests', 'SubscriptionRefundController')
                            ->missing(function () {
                                return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                            });
                        Route::apiResource('payment-methods', 'SubscriptionPaymentMethodController')
                            ->missing(function () {
                                return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                            });
                        Route::apiResource('history', 'SubscriptionHistoryController')
                            ->missing(function () {
                                return JsonResponser::send(true, 'Resource not found', null, Response::HTTP_NOT_FOUND);
                            });
                        Route::group([
                            'prefix' => 'plan',
                        ], function () {
                            Route::get('/current', 'SubscriptionPlanController@view');
                            Route::post('/breakdown', 'SubscriptionPlanController@planBreakdown');
                            Route::post('/change', 'SubscriptionPlanController@create');
                            Route::post('/mark-as-paid', 'SubscriptionPlanController@markAsPaid');
                        });
                    });
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
                    Route::post('/shared/file-upload', 'SharedActionController@uploadFile');
                    Route::match(['get', 'post', 'put', 'delete'], '/shared/{prefix}/{model}/{id}/{action}', 'SharedActionController');
                    // Bulk Upload Routes
                    Route::group([
                        'prefix' => 'shared/bulk-upload',
                        'middleware' => ['auth:api']
                    ], function () {
                        Route::get('/modules', [BulkUploadController::class, 'getAvailableModules']);
                        Route::get('/template/{module}', [BulkUploadController::class, 'downloadTemplate']); // Supports ?type=individual|organization
                        Route::post('/upload', [BulkUploadController::class, 'upload']);
                        Route::get('/jobs', [BulkUploadController::class, 'getUserJobs']);
                        Route::get('/jobs/{id}', [BulkUploadController::class, 'getJobStatus']);
                        Route::get('/jobs-error-report/{id}', [BulkUploadController::class, 'downloadErrorReport']);
                        Route::delete('/jobs/{id}', [BulkUploadController::class, 'deleteJob']);
                    });
                });
            });
        }
    );
    Route::group([
        'prefix' => 'guests',
        "namespace" => "Guest"
    ], function () {
        Route::get('/currencies', 'GuestController@currencies');
        Route::get('/countries', 'GuestController@countries');
        Route::get('/categories', 'GuestController@categories');
        Route::get('/industries', 'GuestController@industries');
        Route::get('/permissions', 'GuestController@allPermissions');
        Route::get('/user/{id}/permissions', 'GuestController@getUserPermissions');
        Route::get('/user/{id}/companies', 'GuestController@getUserCompanies');
        Route::get('/company/{id}/roles', 'GuestController@getCompanyRoles');
        Route::get('/error-logs', 'GuestController@errorLogs');
        Route::post('/check-mail-server', 'GuestController@checkMailServer');
        Route::get('/card-brands', 'GuestController@cardBrands');
        Route::get('/all-banks', 'GuestController@allBanks');
        Route::get('/all-email-templates', 'GuestController@allEmailTemplate');
        Route::get('/fonts', 'GuestController@fonts');
        Route::get('/subscription-plans', 'GuestController@allSubscriptionPlans');
        Route::get('/modules', 'GuestController@modules');
    });
});
