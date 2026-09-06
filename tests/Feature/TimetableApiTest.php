<?php

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\Tenant;
use App\Models\SchoolSite;
use App\Models\TimetableSession;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanGroup;
use App\Models\User;
use App\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

function timetableFixture(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Mathématiques', 'code' => 'MATH', 'duration_hours' => 1, 'price' => 0, 'is_active' => true]);
    $catalogueLevel = CourseLevel::create(['course_id' => $course->id, 'name' => 'Général', 'code' => 'GEN', 'duration_hours' => 1, 'price' => 0, 'is_active' => true]);
    $plan = TrainingPlan::create(['course_level_id' => $catalogueLevel->id, 'teacher_id' => $teacher->id, 'title' => 'Plan', 'status' => 'active']);
    $site = SchoolSite::create(['name' => 'Site principal', 'code' => 'SITE', 'wilaya' => 'Alger', 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => 'Salle 1', 'code' => 'S1', 'type' => 'classroom', 'capacity' => 20]);
    $group = TrainingPlanGroup::create(['training_plan_id' => $plan->id, 'classroom_id' => $room->id, 'group_number' => 1, 'name' => '1AP-A']);
    $period = AcademicPeriod::create(['name' => 'Année scolaire', 'academic_year' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true]);
    app(TenantContext::class)->clear();
    return compact('teacher', 'course', 'room', 'group', 'period');
}

test('admin can open the timetable management page', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($admin)->get('/admin/timetable')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Timetable/Index'));
});

test('admin can create a session using the groups default classroom', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = timetableFixture($tenant);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/timetable/sessions', [
        'training_plan_group_id' => $f['group']->id, 'course_id' => $f['course']->id,
        'teacher_id' => $f['teacher']->id, 'academic_period_id' => $f['period']->id,
        'day' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'recurrence' => 'weekly', 'status' => 'published',
    ]);

    $response->assertCreated()->assertJsonPath('data.room.id', $f['room']->id);
    expect(TimetableSession::withoutGlobalScopes()->first()->tenant_id)->toBe($tenant->id);
});

test('sessions reject overlaps and resources belonging to another tenant', function () {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = timetableFixture($tenant);
    $foreign = timetableFixture($other);
    $payload = ['training_plan_group_id' => $f['group']->id, 'course_id' => $f['course']->id, 'teacher_id' => $f['teacher']->id, 'academic_period_id' => $f['period']->id, 'day' => 2, 'start_time' => '10:00', 'end_time' => '11:00'];

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/timetable/sessions', $payload)->assertCreated();
    $this->postJson('/api/v1/timetable/sessions', [...$payload, 'start_time' => '10:30', 'end_time' => '11:30'])->assertUnprocessable()->assertJsonValidationErrors('conflicts');
    $this->postJson('/api/v1/timetable/sessions', [...$payload, 'course_id' => $foreign['course']->id, 'day' => 3])->assertUnprocessable()->assertJsonValidationErrors('course_id');
});

test('room reservations are linked and disabled rooms are blocked', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = timetableFixture($tenant);
    $payload = ['training_plan_group_id' => $f['group']->id, 'course_id' => $f['course']->id, 'teacher_id' => $f['teacher']->id, 'academic_period_id' => $f['period']->id, 'day' => 4, 'start_time' => '08:00', 'end_time' => '09:00'];

    $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/timetable/sessions', $payload)->assertCreated();
    $session = TimetableSession::find($created->json('data.id'));
    expect($session->reservation)->not->toBeNull()->and($session->reservation->classroom_id)->toBe($f['room']->id);

    app(TenantContext::class)->set($tenant); $f['room']->update(['is_available' => false]); app(TenantContext::class)->clear();
    $this->postJson('/api/v1/timetable/sessions/check-conflicts', [...$payload, 'day' => 5])->assertOk()->assertJsonPath('available', false)->assertJsonPath('conflicts.0.type', 'room_unavailable');
});

test('teacher unavailable periods block weekly sessions', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = timetableFixture($tenant);
    $this->actingAs($admin, 'sanctum')->postJson("/api/v1/timetable/teachers/{$f['teacher']->id}/unavailable-periods", ['starts_at' => '2026-09-07 08:30', 'ends_at' => '2026-09-07 10:00', 'reason' => 'Formation'])->assertCreated();
    $this->postJson('/api/v1/timetable/sessions', ['training_plan_group_id' => $f['group']->id, 'course_id' => $f['course']->id, 'teacher_id' => $f['teacher']->id, 'academic_period_id' => $f['period']->id, 'day' => 1, 'start_time' => '09:00', 'end_time' => '10:00'])->assertUnprocessable()->assertJsonValidationErrors('conflicts');
});

test('teachers have read only timetable access', function () {
    $tenant = Tenant::factory()->create();
    $f = timetableFixture($tenant);
    $this->actingAs($f['teacher'], 'sanctum')->getJson('/api/v1/timetable/catalogue')->assertOk();
    $this->postJson('/api/v1/timetable/sessions', [])->assertForbidden();
});

test('a recurring occurrence can move rooms without changing its weekly series', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = timetableFixture($tenant);
    app(TenantContext::class)->set($tenant);
    $room = Classroom::create(['school_site_id' => $f['room']->school_site_id, 'name' => 'Laboratory 1', 'code' => 'LAB1', 'type' => 'laboratory', 'capacity' => 20]);
    app(TenantContext::class)->clear();
    $payload = ['training_plan_group_id' => $f['group']->id, 'course_id' => $f['course']->id, 'teacher_id' => $f['teacher']->id, 'academic_period_id' => $f['period']->id, 'day' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'published'];
    $baseId = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/timetable/sessions', $payload)->assertCreated()->json('data.id');

    $exceptionId = $this->patchJson("/api/v1/timetable/sessions/{$baseId}/change-room", ['classroom_id' => $room->id, 'temporary' => true, 'effective_date' => '2026-09-07'])->assertCreated()->assertJsonPath('data.change_type', 'temporary_room_change')->json('data.id');

    expect($exceptionId)->not->toBe($baseId)
        ->and(TimetableSession::find($baseId)->classroom_id)->toBe($f['room']->id)
        ->and(TimetableSession::find($exceptionId)->reservation->classroom_id)->toBe($room->id);
    $this->getJson('/api/v1/timetable/calendar?from=2026-09-07&to=2026-09-07')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.room.id', $room->id);
});
