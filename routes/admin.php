<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CarsController;
use App\Http\Controllers\Admin\ReservationsController;
use App\Http\Controllers\Admin\ClientsController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\ExpensesController;

Route::middleware(['auth', 'verified', 'active', 'admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        // Redirect '/admin' to '/admin/cars' with a named route we can reference
        Route::redirect('/', '/admin/cars')->name('home');

        Route::get('settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [CompanySettingsController::class, 'update'])->name('settings.update');

        // Cars
        Route::resource('cars', CarsController::class)->except(['show']);

        // Agency expenses and vehicle maintenance costs
        Route::resource('expenses', ExpensesController::class)->only(['index', 'store', 'update', 'destroy']);

        // Reservations
        Route::resource('reservations', ReservationsController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::get('reservations/{reservation}/print', [ReservationsController::class, 'print'])->name('reservations.print');
        Route::patch('reservations/{reservation}/approve', [ReservationsController::class, 'approve'])->name('reservations.approve');
        Route::patch('reservations/{reservation}/reject', [ReservationsController::class, 'reject'])->name('reservations.reject');
        Route::patch('reservations/{reservation}/cancel', [ReservationsController::class, 'cancel'])->name('reservations.cancel');
        Route::post('reservations/{reservation}/mark-paid', [ReservationsController::class, 'markPaid'])->name('reservations.mark-paid');
        Route::patch('reservations/{reservation}/start', [ReservationsController::class, 'start'])->name('reservations.start');
        Route::patch('reservations/{reservation}/complete', [ReservationsController::class, 'complete'])->name('reservations.complete');
        Route::patch('reservations/{reservation}/no-show', [ReservationsController::class, 'noShow'])->name('reservations.no-show');

        // Clients
        Route::resource('clients', ClientsController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::post('clients/{client}', [ClientsController::class, 'update'])->name('clients.update-upload');
        Route::patch('clients/{client}/suspend', [ClientsController::class, 'suspend'])->name('clients.suspend');
        Route::patch('clients/{client}/activate', [ClientsController::class, 'activate'])->name('clients.activate');
        Route::patch('clients/{client}/reject', [ClientsController::class, 'reject'])->name('clients.reject');
        Route::patch('clients/{client}/drivers/{driver}/approve', [ClientsController::class, 'approveDriver'])->name('clients.drivers.approve');
        Route::patch('clients/{client}/drivers/{driver}/reject', [ClientsController::class, 'rejectDriver'])->name('clients.drivers.reject');

        // Payments
        Route::resource('payments', PaymentsController::class)->only(['index', 'store']);
        Route::patch('payments/{payment}/approve', [PaymentsController::class, 'approve'])->name('payments.approve');
        Route::patch('payments/{payment}/disapprove', [PaymentsController::class, 'disapprove'])->name('payments.disapprove');

        // Reports
        Route::resource('reports', ReportsController::class)->except(['show']);

        // Support
        Route::resource('support', SupportController::class)->only(['index']);
        Route::get('/support/tickets/{ticket}', [SupportController::class, 'show'])
        ->name('support.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportController::class, 'reply'])
        ->name('support.reply');
        Route::post('/support/tickets/{ticket}/close', [SupportController::class, 'close'])
        ->name('support.close');

    });
