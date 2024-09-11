<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\OrderController;
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
    Route::prefix('orders')->group(function () {
        Route::controller(OrderController::class)->group(function(){
            Route::post('/create', 'pushOrder');
            Route::get('/get-all-product', 'getAllProduct');
            Route::get('/fetch-mail-order', 'fetchMailOrder');
            Route::get('/', 'getOrderDB');
            Route::get('/get-providers/{blueprint_id}', 'getProviders');
            Route::post('/create-order-sku', 'createOrderSku');
        });
    });
});

