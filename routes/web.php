<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\ClassroomsController;
use App\Http\Controllers\Admin\CoursesController;
use App\Http\Controllers\Admin\StudentsController;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified', 'active'])->get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::middleware(['auth', 'verified', 'active', 'admin'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {
        Route::get('settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [CompanySettingsController::class, 'update'])->name('settings.update');
        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::post('users', [UsersController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UsersController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/toggle-active', [UsersController::class, 'toggleActive'])->name('users.toggle-active');
        Route::get('classrooms', [ClassroomsController::class, 'index'])->name('classrooms.index');
        Route::post('classrooms', [ClassroomsController::class, 'store'])->name('classrooms.store');
        Route::put('classrooms/{classroom}', [ClassroomsController::class, 'update'])->name('classrooms.update');
        Route::patch('classrooms/{classroom}/toggle-active', [ClassroomsController::class, 'toggleActive'])->name('classrooms.toggle-active');
        Route::get('courses', [CoursesController::class, 'index'])->name('courses.index');
        Route::post('courses', [CoursesController::class, 'store'])->name('courses.store');
        Route::put('courses/{course}', [CoursesController::class, 'update'])->name('courses.update');
        Route::patch('courses/{course}/toggle-active', [CoursesController::class, 'toggleActive'])->name('courses.toggle-active');
        Route::get('students', [StudentsController::class, 'index'])->name('students.index');
        Route::post('students', [StudentsController::class, 'store'])->name('students.store');
        Route::put('students/{student}', [StudentsController::class, 'update'])->name('students.update');
        Route::patch('students/{student}/toggle-active', [StudentsController::class, 'toggleActive'])->name('students.toggle-active');
    });
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
