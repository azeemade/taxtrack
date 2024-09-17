<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\v1\SharedActions\SharedActionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum'); 

Route::group([
    'middleware' => 'api/v1',
    "namespace" => 'App\Http\Controllers\v1'
], function () {
    Route::group([
        'prefix' => 'auth',
        "namespace" => "Auth"
    ], function () {
        Route::post('/login', 'AuthController@login');
        Route::post('/signup', 'AuthController@signup');
        Route::post('/logout', 'AuthController@logout')->middleware('auth:api');
        Route::post('/refresh', 'AuthController@refresh')->middleware('auth:api');
    });
    Route::group([
        'middleware' => 'auth:api'
    ], function () {

        Route::group([
            'prefix' => 'dashboard',
        ], function () {});

        Route::group([
            'prefix' => 'sales',
            "namespace" => "Sales"
        ], function () {

            Route::group([
                'prefix' => 'dashboard',
                "namespace" => "Dashboard"
            ], function () {});
        });

        Route::group([
            'prefix' => 'purchase',
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
            'prefix' => 'budget',
            "namespace" => "Budget"
        ], function () {});

        Route::group([
            'prefix' => 'report',
            "namespace" => "Report"
        ], function () {});

        Route::group([
            'prefix' => 'user-management',
            "namespace" => "UserManagement"
        ], function () {});
        Route::group([
            'prefix' => 'shared',
            "namespace" => "SharedActions"
        ], function () {
            Route::post('{model}/{id}/{action}', 'SharedActionController');
        });
    });
});
