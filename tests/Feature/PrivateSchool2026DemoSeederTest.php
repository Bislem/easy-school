<?php

use App\Models\AcademicYear;
use App\Models\SchoolGroup;
use App\Models\SchoolSubject;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\TimetableSession;
use App\Models\TimetableSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PrivateSchool2026DemoSeeder;

test('private school demo seeder builds a complete idempotent 2026 2027 school year', function () {
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school']);

    $this->seed(PrivateSchool2026DemoSeeder::class);
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::where('name', '2026-2027')->firstOrFail();

    expect($year->status->value)->toBe('active')
        ->and($year->start_date->toDateString())->toBe('2026-09-06')
        ->and($year->periods)->toHaveCount(3)
        ->and($year->calendarEvents)->toHaveCount(2)
        ->and(SchoolSubject::where('code', 'DZ-MATH')->where('title_ar', 'الرياضيات')->exists())->toBeTrue()
        ->and(SchoolGroup::where('academic_year_id', $year->id)->count())->toBe(4)
        ->and(StudentAcademicEnrollment::where('academic_year_id', $year->id)->count())->toBe(80)
        ->and(User::where('role', 'teacher')->count())->toBe(16)
        ->and(TimetableSession::where('academic_year_id', $year->id)->count())->toBe(120)
        ->and(TimetableSetting::first()->time_slots[0])->toMatchArray(['start_time' => '08:00', 'end_time' => '09:00']);

    app(TenantContext::class)->clear();
    $this->seed(PrivateSchool2026DemoSeeder::class);
    app(TenantContext::class)->set($tenant);
    expect(SchoolGroup::where('academic_year_id', $year->id)->count())->toBe(4)
        ->and(StudentAcademicEnrollment::where('academic_year_id', $year->id)->count())->toBe(80)
        ->and(TimetableSession::where('academic_year_id', $year->id)->count())->toBe(120);
});
