<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\ClassroomsController;
use App\Http\Controllers\Admin\CoursesController;
use App\Http\Controllers\Admin\StudentsController;
use App\Http\Controllers\Admin\EnrollmentFormsController;
use App\Http\Controllers\Admin\ExpensesController;
use App\Http\Controllers\Admin\SalariesController;
use App\Http\Controllers\PublicEnrollmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\TrainingPlansController;

Route::redirect('/', '/login')->name('home');

Route::get('inscription/confirmer/{enrollment}/{token}', [PublicEnrollmentController::class, 'confirm'])->middleware('signed')->name('public.enrollment.confirm');
Route::get('inscription/{enrollmentForm:public_token}', [PublicEnrollmentController::class, 'show'])->name('public.enrollment.show');
Route::post('inscription/{enrollmentForm:public_token}', [PublicEnrollmentController::class, 'store'])->middleware('throttle:10,1')->name('public.enrollment.store');

Route::middleware(['auth', 'verified', 'active'])->get('/dashboard', DashboardController::class)->name('dashboard');

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
        Route::get('enrollment-forms', [EnrollmentFormsController::class, 'index'])->name('enrollment-forms.index');
        Route::get('enrollment-forms/{enrollmentForm}', [EnrollmentFormsController::class, 'show'])->name('enrollment-forms.show');
        Route::post('enrollment-forms', [EnrollmentFormsController::class, 'store'])->name('enrollment-forms.store');
        Route::put('enrollment-forms/{enrollmentForm}', [EnrollmentFormsController::class, 'update'])->name('enrollment-forms.update');
        Route::patch('enrollment-forms/{enrollmentForm}/toggle-active', [EnrollmentFormsController::class, 'toggleActive'])->name('enrollment-forms.toggle-active');
        Route::patch('enrollment-forms/{enrollmentForm}/enrollments/{enrollment}/group', [EnrollmentFormsController::class, 'updateGroup'])->name('enrollment-forms.enrollments.group');
        Route::post('enrollment-forms/{enrollmentForm}/enrollments', [EnrollmentFormsController::class, 'addEnrollment'])->name('enrollment-forms.enrollments.store');
        Route::delete('enrollment-forms/{enrollmentForm}/enrollments/{enrollment}', [EnrollmentFormsController::class, 'removeEnrollment'])->name('enrollment-forms.enrollments.destroy');
        Route::get('expenses', [ExpensesController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpensesController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpensesController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpensesController::class, 'destroy'])->name('expenses.destroy');
        Route::get('salaries', [SalariesController::class, 'index'])->name('salaries.index');
        Route::post('salaries', [SalariesController::class, 'store'])->name('salaries.store');
        Route::put('salaries/{salary}', [SalariesController::class, 'update'])->name('salaries.update');
        Route::delete('salaries/{salary}', [SalariesController::class, 'destroy'])->name('salaries.destroy');
        Route::get('planifications', [TrainingPlansController::class, 'index'])->name('training-plans.index');
        Route::post('planifications', [TrainingPlansController::class, 'store'])->name('training-plans.store');
        Route::get('planifications/{trainingPlan}', [TrainingPlansController::class, 'show'])->name('training-plans.show');
        Route::put('planifications/{trainingPlan}', [TrainingPlansController::class, 'update'])->name('training-plans.update');
        Route::post('planifications/{trainingPlan}/groupes', [TrainingPlansController::class, 'storeGroup'])->name('training-plans.groups.store');
        Route::put('planifications/{trainingPlan}/groupes/{group}', [TrainingPlansController::class, 'updateGroup'])->name('training-plans.groups.update');
        Route::delete('planifications/{trainingPlan}/groupes/{group}', [TrainingPlansController::class, 'destroyGroup'])->name('training-plans.groups.destroy');
        Route::post('planifications/{trainingPlan}/groupes/{group}/seances', [TrainingPlansController::class, 'storeSession'])->name('training-plans.sessions.store');
        Route::put('planifications/{trainingPlan}/groupes/{group}/seances/{session}', [TrainingPlansController::class, 'updateSession'])->name('training-plans.sessions.update');
        Route::delete('planifications/{trainingPlan}/groupes/{group}/seances/{session}', [TrainingPlansController::class, 'destroySession'])->name('training-plans.sessions.destroy');
    });
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
