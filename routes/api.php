<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
Route::post('/payment/callback', [PaymentController::class, 'callback']);
Route::get('/payment/status',    [PaymentController::class, 'checkStatus']);
