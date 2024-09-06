<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function(){
        Route::post('register', 'register');
        Route::post('login', 'login');
    });
});

Route::prefix('orders')->group(function () {
    Route::controller(OrderController::class)->group(function(){
        Route::post('/create', 'createOrder');
    });
});

Route::prefix('mails')->group(function () {
    Route::controller(MailController::class)->group(function(){
        Route::get('/fetch-mail-order', 'fetchOrders');
    });
});

