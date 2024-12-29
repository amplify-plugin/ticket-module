<?php

use Amplify\System\Ticket\Controllers\TicketController;
use Amplify\System\Ticket\Controllers\TicketCrudController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'backpack'),
    'middleware' => array_merge(config('backpack.base.web_middleware', ['web']),
        (array) config('backpack.base.middleware_key', 'admin')),
    ['admin_password_reset_required'],
    'namespace' => 'Amplify\System\Ticket\Controllers',
], function () {
    Route::crud('ticket', 'TicketCrudController');
    Route::crud('ticket-department', 'TicketDepartmentCrudController');
});

Route::controller(TicketCrudController::class)->prefix('ticket')->as('admin.')->group(function () {
    Route::get('/{id}', 'show')->name('ticket');
    Route::post('/{id}', 'store')->name('ticket.store');
});

Route::post('/tickets-store', [TicketController::class, 'newTicket'])->name('tickets.store');
Route::post('/tickets-reply/{thread}', [TicketController::class, 'replyToTicket'])->name('tickets.reply');

Route::name('frontend.tickets.')->middleware(['web', ProtectAgainstSpam::class, 'customers'])->resource('tickets', TicketController::class);
