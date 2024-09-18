<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function(){
        Route::post('register', 'register');
        Route::post('login', 'login');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('isAdmin')->group(function () {
        Route::prefix('users')->group(function () {
            Route::controller(UserController::class)->group(function(){
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/create', 'create');
                Route::get('/{id}', 'edit');
                Route::delete('/{id}', 'destroy');
            });
        });
    });

    Route::prefix('orders')->group(function () {
        Route::controller(OrderController::class)->group(function(){
            Route::post('/create', 'pushOrder');
            Route::get('/', 'getOrderDB');
            Route::get('/get-providers/{blueprint_id}', 'getProviders');
            Route::post('/create-order-sku', 'createOrderSku');
            Route::post('/store-blueprint', 'storeBlueprint');
            Route::post('/approval-order', 'approvalOrder');
        });
        Route::controller(MailController::class)->group(function(){
            Route::get('/fetch-mail-order', 'fetchMailOrder');
        });
    });

    
});

