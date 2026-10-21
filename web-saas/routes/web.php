<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/system/health', [DashboardController::class, 'systemHealth'])->name('system.health');

// Workspace Role (Admin Console vs Operator Desk) & Multi-Tenant Switcher
Route::post('/workspace/role', [DashboardController::class, 'switchRole'])->name('workspace.role');
Route::post('/workspace/tenant', [DashboardController::class, 'switchTenant'])->name('workspace.tenant');

// Knowledge Base (RAG Documents — Admin Only)
Route::post('/documents', [DashboardController::class, 'storeDocument'])->name('documents.store');
Route::post('/documents/{document}/reindex', [DashboardController::class, 'reindexDocument'])->name('documents.reindex');
Route::delete('/documents/{document}', [DashboardController::class, 'destroyDocument'])->name('documents.destroy');

// AI Provider Settings (OpenRouter, OpenCode, Claude, OpenAI, Mock) & RAG Sandbox
Route::post('/settings/ai', [DashboardController::class, 'updateAiSettings'])->name('settings.ai.update');
Route::post('/sandbox/query', [DashboardController::class, 'querySandbox'])->name('sandbox.query');

// Human-in-the-Loop Live Inbox Operator Reply & Status Control
Route::post('/tickets/{ticket}/reply', [DashboardController::class, 'replyToTicket'])->name('tickets.reply');
Route::post('/tickets/{ticket}/status', [DashboardController::class, 'updateTicketStatus'])->name('tickets.status');

// Stripe Billing Checkout
Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
