<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Task\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum'); 

Route::group([
    'middleware' => 'api',
], function () {
    Route::group([
        'prefix' => 'auth'
    ], function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/signup', [AuthController::class, 'signup']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    });
    // Route::group([
    //     'prefix' => 'tasks',
    //     'middleware' => 'auth:api'
    // ], function () {
        // Route::get('/', [TaskController::class, 'index']);
        // Route::get('/{id}', [TaskController::class, 'show']);
        // Route::post('/create', [TaskController::class, 'create']);
        // Route::put('/update/{id}', [TaskController::class, 'update']);
        // Route::delete('/destroy/{id}', [TaskController::class, 'destroy']);
        Route::resource('tasks', TaskController::class)->middleware('auth:api');
    // });
});
