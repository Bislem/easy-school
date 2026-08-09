<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use App\Http\Controllers\Settings\DrivingLicenseController;
use App\Http\Controllers\Settings\DriverController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware(['active', 'client'])->group(function () {
        Route::get('settings/driving-license', [DrivingLicenseController::class, 'edit'])->name('driving-license.edit');
        Route::post('settings/driving-license', [DrivingLicenseController::class, 'update'])->name('driving-license.update');
        Route::get('settings/drivers', [DriverController::class, 'index'])->name('drivers.index');
        Route::post('settings/drivers', [DriverController::class, 'store'])->name('drivers.store');
        Route::post('settings/drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
        Route::delete('settings/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');
    });

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
