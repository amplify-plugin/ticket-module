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

Route::middleware(['web', 'customers', 'throttle:60,1'])->group(function () {
    Route::get('tickets/{thread}/messages', [TicketController::class, 'messages'])
        ->whereNumber('thread')
        ->name('frontend.tickets.messages');
    Route::post('tickets/{thread}/messages', [TicketController::class, 'reply'])
        ->whereNumber('thread')
        ->name('frontend.tickets.messages.store');
});

Route::middleware(array_merge(
    (array) config('backpack.base.web_middleware', ['web']),
    (array) config('backpack.base.middleware_key', 'admin'),
    ['admin_password_reset_required', 'throttle:60,1']
))->prefix(config('backpack.base.route_prefix', 'admin'))->group(function () {
    Route::get('ticket/{thread}/messages', [TicketCrudController::class, 'messages'])
        ->whereNumber('thread')
        ->name('admin.ticket.messages');
    Route::post('ticket/{thread}/messages', [TicketCrudController::class, 'reply'])
        ->whereNumber('thread')
        ->name('admin.ticket.messages.store');
});

Route::post('/tickets-store', [TicketController::class, 'newTicket'])->name('tickets.store');
Route::post('/tickets-reply/{thread}', [TicketController::class, 'replyToTicket'])->name('tickets.reply');

Route::name('frontend.')->middleware(['web', ProtectAgainstSpam::class, 'customers'])->group(function() {
    Route::resource('tickets', TicketController::class);
});
