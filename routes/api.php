<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Facebook Messenger webhook (set the Callback URL to https://<your-domain>/api/webhook/facebook)
Route::get('/webhook/facebook', [WebhookController::class, 'verify'])->name('webhook.verify');
Route::post('/webhook/facebook', [WebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhook.receive');
