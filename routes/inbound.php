<?php

use Escalated\Laravel\Http\Controllers\InboundEmailController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound Email Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle inbound email webhooks from external services
| (Mailgun, Postmark, SES). They must NOT require authentication
| since external services POST to them.
|
*/

Route::middleware(['api'])
    ->prefix(config('escalated.routes.prefix', 'support').'/inbound')
    ->group(function () {
        Route::post('/{adapter}', [InboundEmailController::class, 'webhook'])
            ->where('adapter', 'mailgun|postmark|ses')
            ->name('escalated.inbound.webhook');
    });
