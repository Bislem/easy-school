<?php

use App\Http\Controllers\Api\AcademicPeriodController;
use App\Http\Controllers\Api\Mobile\V1\AuthController;
use App\Http\Controllers\Api\Mobile\V1\CommunicationController;
use App\Http\Controllers\Api\Mobile\V1\DeviceController;
use App\Http\Controllers\Api\Mobile\V1\NotificationController;
use App\Http\Controllers\Api\Mobile\V1\ParentController;
use App\Http\Controllers\Api\Mobile\V1\SchoolController;
use App\Http\Controllers\Api\TeacherAvailabilityController;
use App\Http\Controllers\Api\TimetableCatalogueController;
use App\Http\Controllers\Api\TimetableSessionController;
use App\Http\Controllers\Api\TimetableSettingController;
use Illuminate\Support\Facades\Route;

// The timetable is a first-party admin SPA. Load the web session explicitly so
// authentication does not depend on SANCTUM_STATEFUL_DOMAINS matching the
// deployment hostname (a common source of production-only 401 responses).
Route::middleware(['web', 'auth:sanctum,web', \App\Http\Middleware\SetTenantContext::class])->prefix('v1/timetable')->name('api.v1.timetable.')->group(function () {
    Route::get('catalogue', TimetableCatalogueController::class)->name('catalogue');
    Route::get('settings', [TimetableSettingController::class, 'show'])->name('settings.show');
    Route::put('settings', [TimetableSettingController::class, 'update'])->name('settings.update');
    Route::put('groups/{group}/defaults', [TimetableSettingController::class, 'updateGroupDefaults'])->name('groups.defaults.update');
    Route::get('teachers/{teacher}/availability', [TeacherAvailabilityController::class, 'index'])->name('teachers.availability.index');
    Route::post('teachers/{teacher}/availability', [TeacherAvailabilityController::class, 'storeWeekly'])->name('teachers.availability.store');
    Route::post('teachers/{teacher}/unavailable-periods', [TeacherAvailabilityController::class, 'storeUnavailable'])->name('teachers.unavailable-periods.store');
    Route::delete('teacher-availabilities/{availability}', [TeacherAvailabilityController::class, 'destroyWeekly'])->name('teacher-availabilities.destroy');
    Route::delete('teacher-unavailable-periods/{unavailablePeriod}', [TeacherAvailabilityController::class, 'destroyUnavailable'])->name('teacher-unavailable-periods.destroy');
    Route::post('sessions/check-conflicts', [TimetableSessionController::class, 'check'])->name('sessions.check');
    Route::get('calendar', [TimetableSessionController::class, 'calendar'])->name('calendar');
    Route::post('sessions/{timetableSession}/duplicate', [TimetableSessionController::class, 'duplicate'])->name('sessions.duplicate');
    Route::patch('sessions/{timetableSession}/move', [TimetableSessionController::class, 'move'])->name('sessions.move');
    Route::patch('sessions/{timetableSession}/replace-teacher', [TimetableSessionController::class, 'replaceTeacher'])->name('sessions.replace-teacher');
    Route::patch('sessions/{timetableSession}/change-room', [TimetableSessionController::class, 'changeRoom'])->name('sessions.change-room');
    Route::patch('sessions/{timetableSession}/cancel', [TimetableSessionController::class, 'cancel'])->name('sessions.cancel');
    Route::apiResource('academic-periods', AcademicPeriodController::class)->parameters(['academic-periods' => 'academicPeriod']);
    Route::apiResource('sessions', TimetableSessionController::class)->parameters(['sessions' => 'timetableSession']);
});

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1')->name('password.reset');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('contexts', [AuthController::class, 'contexts'])->name('contexts');
        Route::get('profile', [AuthController::class, 'profile'])->name('profile.show');
        Route::patch('profile', [AuthController::class, 'updateProfile'])->middleware('throttle:10,1')->name('profile.update');
        Route::post('context', [AuthController::class, 'selectContext'])->name('context.select');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout.all');
        Route::put('password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1')->name('password.update');
    });
    Route::middleware(['auth:sanctum', 'mobile-context', 'mobile-access'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::get('school', SchoolController::class)->name('school');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::get('announcements', [NotificationController::class, 'announcements']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
        Route::get('conversations', [CommunicationController::class, 'index']);
        Route::post('conversations', [CommunicationController::class, 'store']);
        Route::get('conversations/unread-count', [CommunicationController::class, 'unread']);
        Route::get('conversations/{ticket}/messages', [CommunicationController::class, 'show']);
        Route::post('conversations/{ticket}/messages', [CommunicationController::class, 'reply']);

        Route::middleware('mobile-role:parent')->prefix('parent')->group(function () {
            Route::get('children', [ParentController::class, 'children']);
            Route::get('children/{student}', [ParentController::class, 'show']);
            Route::get('children/{student}/formations', [ParentController::class, 'formations']);
            Route::get('children/{student}/planning', [ParentController::class, 'planning']);
            Route::get('children/{student}/attendance', [ParentController::class, 'attendance']);
            Route::get('children/{student}/grades', [ParentController::class, 'grades']);
            Route::get('children/{student}/observations', [ParentController::class, 'observations']);
            Route::post('children/{student}/observations/{observation}/replies', [ParentController::class, 'replyToObservation'])->middleware('throttle:10,1');
            Route::get('children/{student}/certificates', [ParentController::class, 'certificates']);
            Route::get('children/{student}/documents', [ParentController::class, 'documents']);
            Route::get('children/{student}/payments', [ParentController::class, 'payments']);
        });
    });
});
