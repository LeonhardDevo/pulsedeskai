<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\InternalTelegramController;
use Illuminate\Support\Facades\Route;

// Internal Microservice Routes (Node.js Telegram Bot <-> Laravel SaaS)
Route::prefix('internal/telegram')->group(function () {
    Route::get('/tenant/{slug}', [InternalTelegramController::class, 'tenantSettings']);
    Route::post('/message', [InternalTelegramController::class, 'syncMessage']);
    Route::post('/escalate', [InternalTelegramController::class, 'escalate']);
    Route::post('/resolve', [InternalTelegramController::class, 'resolve']);
});

// Stripe Webhook Receiver
Route::post('/stripe/webhook', [BillingController::class, 'webhook']);
