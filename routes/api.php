<?php

use App\Http\Controllers\Api\MockPaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/mock-payments', [MockPaymentController::class, 'store'])
    ->middleware('mock-payment.token')
    ->name('api.mock-payments.store');
