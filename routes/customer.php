<?php

use Escalated\Laravel\Http\Controllers\CustomerTicketController;
use Escalated\Laravel\Http\Middleware\ResolveTicketByReference;
use Illuminate\Support\Facades\Route;

Route::middleware(config('escalated.routes.middleware', ['web', 'auth']))
    ->prefix(config('escalated.routes.prefix', 'support'))
    ->group(function () {
        Route::get('/', [CustomerTicketController::class, 'index'])->name('escalated.customer.tickets.index');
        Route::get('/create', [CustomerTicketController::class, 'create'])->name('escalated.customer.tickets.create');
        Route::post('/', [CustomerTicketController::class, 'store'])->name('escalated.customer.tickets.store');

        Route::middleware(ResolveTicketByReference::class)
            ->where(['ticket' => '[A-Z]+-[0-9]+|[0-9]+'])
            ->group(function () {
                Route::get('/{ticket}', [CustomerTicketController::class, 'show'])->name('escalated.customer.tickets.show');
                Route::post('/{ticket}/reply', [CustomerTicketController::class, 'reply'])->name('escalated.customer.tickets.reply');
                Route::post('/{ticket}/close', [CustomerTicketController::class, 'close'])->name('escalated.customer.tickets.close');
                Route::post('/{ticket}/reopen', [CustomerTicketController::class, 'reopen'])->name('escalated.customer.tickets.reopen');
            });
    });
