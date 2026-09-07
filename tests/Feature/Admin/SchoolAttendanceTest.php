<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AttendanceException;
use App\Models\Classroom;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\SchoolSubject;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Tenancy\TenantContext;

function schoolAttendanceFixture(Tenant $tenant, string $yearName = '2026-2027'): array
{
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::create(['name' => $yearName, 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $cycle = SchoolCycle::create(['name' => 'Primaire', 'code' => 'P-'.$yearName, 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '1AP', 'code' => '1AP-'.$yearName, 'sort_order' => 1]);
    $site = SchoolSite::create(['name' => 'Site', 'code' => 'SITE-'.$yearName, 'wilaya' => 'Alger', 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => 'Salle', 'code' => 'S-'.$yearName, 'type' => 'classroom', 'capacity' => 20]);
    $group = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'classroom_id' => $room->id, 'name' => '1AP-A', 'code' => 'G-'.$yearName, 'capacity' => 20]);
    $subject = SchoolSubject::create(['title' => 'Mathématiques', 'code' => 'M-'.$yearName, 'duration_hours' => 1, 'price' => 0, 'is_active' => true]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $student = Student::create(['first_name' => 'Amine', 'last_name' => 'Test', 'email' => fake()->unique()->safeEmail(), 'phone' => '0550000000', 'registration_date' => '2026-09-01', 'status' => 'active', 'is_active' => true]);
    StudentAcademicEnrollment::create(['academic_year_id' => $year->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'school_group_id' => $group->id, 'status' => 'enrolled', 'enrollment_date' => '2026-09-01']);
    $session = TimetableSession::create(['academic_year_id' => $year->id, 'school_group_id' => $group->id, 'course_id' => $subject->id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'day' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'recurrence' => 'weekly', 'status' => 'published']);
    app(TenantContext::class)->clear();

    return compact('tenant', 'year', 'group', 'teacher', 'admin', 'student', 'session');
}

test('presence is implicit and creates no row', function () {
    $f = schoolAttendanceFixture(Tenant::factory()->create());
    app(TenantContext::class)->set($f['tenant']);
    expect(app(AttendanceService::class)->getStudentStatus($f['student'], $f['session'], '2026-09-07'))->toBe('PRESENT')
        ->and(app(AttendanceService::class)->getTeacherStatus($f['teacher'], $f['session'], '2026-09-07'))->toBe('PRESENT')
        ->and(AttendanceException::count())->toBe(0);
});

test('student session absence and late status are resolved', function () {
    $f = schoolAttendanceFixture(Tenant::factory()->create());
    app(TenantContext::class)->set($f['tenant']);
    $row = AttendanceException::create(['academic_year_id' => $f['year']->id, 'date' => '2026-09-07', 'timetable_session_id' => $f['session']->id, 'person_type' => 'STUDENT', 'student_id' => $f['student']->id, 'status' => 'ABSENT', 'created_by' => $f['admin']->id]);
    expect(app(AttendanceService::class)->getStudentStatus($f['student'], $f['session'], '2026-09-07'))->toBe('ABSENT');
    $row->update(['status' => 'LATE', 'minutes_late' => 15]);
    expect(app(AttendanceService::class)->getStudentStatus($f['student'], $f['session'], '2026-09-07'))->toBe('LATE')->and($row->fresh()->minutes_late)->toBe(15);
});

test('student full day exception applies to every session', function () {
    $f = schoolAttendanceFixture(Tenant::factory()->create());
    app(TenantContext::class)->set($f['tenant']);
    AttendanceException::create(['academic_year_id' => $f['year']->id, 'date' => '2026-09-07', 'person_type' => 'STUDENT', 'student_id' => $f['student']->id, 'status' => 'EXCUSED', 'created_by' => $f['admin']->id]);
    expect(app(AttendanceService::class)->getStudentStatus($f['student'], $f['session'], '2026-09-07'))->toBe('EXCUSED');
});

test('teacher session and date range exceptions are resolved without changing timetable', function () {
    $f = schoolAttendanceFixture(Tenant::factory()->create());
    app(TenantContext::class)->set($f['tenant']);
    AttendanceException::create(['academic_year_id' => $f['year']->id, 'date' => '2026-09-07', 'timetable_session_id' => $f['session']->id, 'person_type' => 'TEACHER', 'teacher_id' => $f['teacher']->id, 'status' => 'ABSENT', 'created_by' => $f['admin']->id]);
    expect(app(AttendanceService::class)->getTeacherStatus($f['teacher'], $f['session'], '2026-09-07'))->toBe('ABSENT');
    AttendanceException::whereNotNull('timetable_session_id')->delete();
    AttendanceException::create(['academic_year_id' => $f['year']->id, 'date' => '2026-09-06', 'end_date' => '2026-09-12', 'person_type' => 'TEACHER', 'teacher_id' => $f['teacher']->id, 'status' => 'EXCUSED', 'created_by' => $f['admin']->id]);
    expect(app(AttendanceService::class)->getTeacherStatus($f['teacher'], $f['session'], '2026-09-07'))->toBe('EXCUSED')->and($f['session']->fresh()->status->value)->toBe('published');
});

test('exceptions are isolated by academic year', function () {
    $tenant = Tenant::factory()->create();
    $f = schoolAttendanceFixture($tenant);
    app(TenantContext::class)->set($tenant);
    $otherYear = AcademicYear::create(['name' => '2025-2026', 'start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'status' => 'closed']);
    AttendanceException::create(['academic_year_id' => $otherYear->id, 'date' => '2026-09-07', 'person_type' => 'STUDENT', 'student_id' => $f['student']->id, 'status' => 'ABSENT', 'created_by' => $f['admin']->id]);
    expect(app(AttendanceService::class)->getStudentStatus($f['student'], $f['session'], '2026-09-07'))->toBe('PRESENT');
});

test('attendance exceptions are isolated by tenant', function () {
    $first = schoolAttendanceFixture(Tenant::factory()->create(), '2026-2027-A');
    $secondTenant = Tenant::factory()->create();
    schoolAttendanceFixture($secondTenant, '2026-2027-B');
    app(TenantContext::class)->set($first['tenant']);
    AttendanceException::create(['academic_year_id' => $first['year']->id, 'date' => '2026-09-07', 'person_type' => 'STUDENT', 'student_id' => $first['student']->id, 'status' => 'ABSENT', 'created_by' => $first['admin']->id]);
    app(TenantContext::class)->set($secondTenant);
    expect(AttendanceException::count())->toBe(0);
});

test('private school admin can open attendance screen defaulting to today', function () {
    $tenant = Tenant::factory()->create();
    $f = schoolAttendanceFixture($tenant);
    $this->travelTo('2026-09-07');
    $this->actingAs($f['admin'])->get('/admin/school-attendance')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/SchoolAttendance/Index')->where('date', '2026-09-07')->where('view', 'students'));
});
