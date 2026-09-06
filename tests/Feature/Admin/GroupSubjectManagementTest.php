<?php

use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\SchoolCycle;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TrainingPlanGroup;
use App\Models\User;
use App\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can open group and subject management modules', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $this->actingAs($admin)->get('/admin/groups')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Groups/Index'));
    $this->get('/admin/subjects')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Subjects/Index'));
});

function schoolManagementFixture(Tenant $tenant): array
{
    app(TenantContext::class)->set($tenant);
    $cycle = SchoolCycle::create(['name' => 'CEM', 'code' => 'CEM', 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '2AM', 'code' => '2AM', 'sort_order' => 1]);
    $period = AcademicPeriod::create(['name' => 'Année scolaire', 'academic_year' => '2026-2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_current' => true]);
    $site = SchoolSite::create(['name' => 'Principal', 'code' => 'MAIN', 'wilaya' => 'Alger', 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => 'Salle 12', 'code' => 'S12', 'capacity' => 30, 'is_active' => true, 'is_available' => true]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    app(TenantContext::class)->clear();

    return compact('cycle', 'level', 'period', 'room', 'teacher');
}

test('admin creates an independent group with a principal teacher', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = schoolManagementFixture($tenant);
    $this->actingAs($admin)->post('/admin/groups', ['name' => '2AM-B', 'code' => '2AM-B', 'school_level_id' => $f['level']->id, 'academic_period_id' => $f['period']->id, 'classroom_id' => $f['room']->id, 'capacity' => 25, 'is_active' => true, 'teacher_ids' => [], 'principal_teacher_id' => $f['teacher']->id])->assertSessionHasNoErrors();
    $group = TrainingPlanGroup::where('code', '2AM-B')->first();
    expect($group->training_plan_id)->toBeNull()->and($group->principal_teacher_id)->toBe($f['teacher']->id)->and($group->teachers()->whereKey($f['teacher']->id)->exists())->toBeTrue();
});

test('group roster enforces capacity and one group per student', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = schoolManagementFixture($tenant);
    app(TenantContext::class)->set($tenant);
    $first = TrainingPlanGroup::create(['school_level_id' => $f['level']->id, 'academic_period_id' => $f['period']->id, 'group_number' => 1, 'name' => 'A', 'code' => 'A', 'capacity' => 1]);
    $second = TrainingPlanGroup::create(['school_level_id' => $f['level']->id, 'academic_period_id' => $f['period']->id, 'group_number' => 2, 'name' => 'B', 'code' => 'B', 'capacity' => 20]);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Ali', 'email' => 'lina@school.test', 'phone' => '0550000000', 'is_active' => true]);
    app(TenantContext::class)->clear();
    $this->actingAs($admin)->post("/admin/groups/{$first->id}/students", ['student_ids' => [$student->id]])->assertSessionHasNoErrors();
    $this->post("/admin/groups/{$second->id}/students", ['student_ids' => [$student->id]])->assertSessionHasErrors('student_ids');
    expect($student->fresh()->training_plan_group_id)->toBe($first->id);

    $this->post("/admin/groups/{$second->id}/students", ['student_ids' => [$student->id], 'move_existing' => true])->assertSessionHasNoErrors();
    expect($student->fresh()->training_plan_group_id)->toBe($second->id);
});

test('levels support configurable specializations sharing the same base code', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = schoolManagementFixture($tenant);

    $payload = [
        'school_cycle_id' => $f['cycle']->id,
        'name' => '2AS',
        'code' => '2AS',
        'is_active' => true,
    ];
    $this->actingAs($admin)->post('/admin/school-levels', [...$payload, 'specialization' => 'Sciences expérimentales'])->assertSessionHasNoErrors();
    $this->post('/admin/school-levels', [...$payload, 'specialization' => 'Mathématiques'])->assertSessionHasNoErrors();

    expect(SchoolLevel::where('code', '2AS')->whereNotNull('specialization')->count())->toBe(2);
});

test('subjects are assigned to levels teachers and room requirements', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = schoolManagementFixture($tenant);
    $this->actingAs($admin)->post('/admin/subjects', ['title' => 'Sciences physiques', 'code' => 'PHY', 'category' => 'Sciences', 'color' => '#2563eb', 'weekly_hours' => 3, 'description' => null, 'required_room_types' => ['laboratory'], 'is_specialized' => true, 'is_active' => true, 'school_level_ids' => [$f['level']->id], 'teacher_ids' => [$f['teacher']->id]])->assertSessionHasNoErrors();
    $subject = Course::where('code', 'PHY')->first();
    expect($subject->schoolLevels()->whereKey($f['level']->id)->exists())->toBeTrue()->and($subject->teachers()->whereKey($f['teacher']->id)->exists())->toBeTrue()->and($subject->required_room_types)->toBe(['laboratory']);
});

test('room management stores scheduling type and reservation availability', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $f = schoolManagementFixture($tenant);

    $this->actingAs($admin)->post('/admin/classrooms', [
        'school_site_id' => $f['room']->school_site_id,
        'name' => 'Laboratoire central',
        'code' => 'LAB-C',
        'type' => RoomType::LABORATORY->value,
        'capacity' => 24,
        'location' => 'Bloc sciences',
        'description' => null,
        'is_active' => true,
        'is_available' => false,
    ])->assertSessionHasNoErrors();

    $room = Classroom::where('code', 'LAB-C')->firstOrFail();
    expect($room->type)->toBe(RoomType::LABORATORY)
        ->and($room->is_available)->toBeFalse();
});

test('group management route model binding remains tenant isolated', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $foreign = schoolManagementFixture($otherTenant);

    app(TenantContext::class)->set($otherTenant);
    $foreignGroup = TrainingPlanGroup::create([
        'school_level_id' => $foreign['level']->id,
        'academic_period_id' => $foreign['period']->id,
        'group_number' => 1,
        'name' => 'Groupe étranger',
        'code' => 'FOREIGN',
        'capacity' => 20,
    ]);
    app(TenantContext::class)->clear();

    $this->actingAs($admin)->get("/admin/groups/{$foreignGroup->id}")->assertNotFound();
});
