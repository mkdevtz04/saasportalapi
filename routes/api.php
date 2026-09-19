<?php

use App\Http\Controllers\Api\RouterAgentController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// The gateway calls this itself, so it has no language.
Route::post('/payment/callback', [PaymentController::class, 'callback']);

// Called by the captive portal page, in the customer's language.
Route::middleware('portal.locale')->group(function () {
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
    Route::get('/payment/status',    [PaymentController::class, 'checkStatus']);
    Route::get('/access/status',     [PaymentController::class, 'accessStatus'])->middleware('throttle:access');

    // Voucher redemption, called when a customer enters a code.
    Route::post('/voucher/redeem',   [VoucherController::class, 'redeem'])->middleware('throttle:voucher');
});

// Router heartbeat and commands. Called by a script on the router every minute.
Route::get('/agent/{token}/poll', [RouterAgentController::class, 'poll'])
    ->middleware('throttle:router-agent')
    ->name('router.agent.poll');
