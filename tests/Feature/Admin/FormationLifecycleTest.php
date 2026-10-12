<?php

use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\CourseEnrollment;
use App\Models\CourseLevel;
use App\Models\EnrollmentForm;
use App\Models\Formation;
use App\Models\Role;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\TenantRbac;
use App\Tenancy\TenantContext;

function formationSetup(string $suffix = 'a'): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'training_center']);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $formation = Formation::create(['title' => "Anglais {$suffix}", 'code' => "EN-{$suffix}", 'duration_hours' => 40, 'price' => 20000, 'is_active' => true]);
    $level = CourseLevel::create(['course_id' => $formation->id, 'name' => 'A1', 'code' => "A1-{$suffix}", 'duration_hours' => 10, 'price' => 20000, 'is_active' => true]);
    $site = SchoolSite::create(['name' => "Site {$suffix}", 'code' => "S-{$suffix}", 'wilaya' => 'Alger', 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => "Salle {$suffix}", 'code' => "R-{$suffix}", 'capacity' => 2, 'is_active' => true]);
    $session = EnrollmentForm::create(['course_id' => $formation->id, 'teacher_id' => $teacher->id, 'title' => "Session {$suffix}", 'start_date' => today()->addDays(5), 'end_date' => today()->addMonths(2), 'min_students' => 1, 'max_students' => 10, 'is_active' => true]);

    return compact('tenant', 'admin', 'teacher', 'formation', 'level', 'room', 'session');
}

function sessionPlan(array $f, int $groups = 2): TrainingPlan
{
    test()->actingAs($f['admin'])->post(route('admin.training-plans.store'), ['source_type' => 'form', 'enrollment_form_id' => $f['session']->id, 'course_level_id' => $f['level']->id, 'title' => 'Plan '.uniqid(), 'groups_count' => $groups, 'classroom_id' => $f['room']->id])->assertSessionHasNoErrors();
    // Requests clear the tenant context on exit; records created by the test need it back.
    app(TenantContext::class)->set($f['tenant']);

    return TrainingPlan::latest('id')->firstOrFail();
}

function formationStudent(string $first, ?string $email = 'famille@example.test'): Student
{
    return Student::create(['first_name' => $first, 'last_name' => 'Kaci', 'email' => $email, 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
}

test('formation references from another school are rejected', function () {
    $other = formationSetup('other');
    $f = formationSetup('mine');
    $this->actingAs($f['admin'])->post(route('admin.enrollment-forms.store'), [
        'course_id' => $f['formation']->id, 'teacher_id' => $other['teacher']->id, 'classroom_id' => $other['room']->id,
        'title' => 'Session', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01', 'min_students' => 1, 'max_students' => 10, 'is_active' => true, 'cover_temp_folders' => ['x'],
    ])->assertSessionHasErrors(['teacher_id', 'classroom_id']);

    $plan = sessionPlan($f, 1);
    $this->post(route('admin.training-plans.sessions.store', [$plan, $plan->groups()->first()]), ['title' => 'S1', 'classroom_id' => $other['room']->id, 'teacher_id' => $other['teacher']->id, 'starts_at' => today()->addDays(6)->setTime(9, 0), 'ends_at' => today()->addDays(6)->setTime(10, 0)])
        ->assertSessionHasErrors(['classroom_id', 'teacher_id']);
});

test('siblings sharing an e-mail get separate student files and registrations', function () {
    $f = formationSetup();
    foreach (['Lina', 'Yanis'] as $first) {
        $this->actingAs($f['admin'])->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['first_name' => $first, 'last_name' => 'Kaci', 'email' => 'famille@example.test', 'phone' => '0550000000'])->assertSessionHasNoErrors();
    }

    expect(Student::where('email', 'famille@example.test')->count())->toBe(2)
        ->and($f['session']->registeredCount())->toBe(2);
    $lina = Student::where('first_name', 'Lina')->firstOrFail();
    $this->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['student_id' => $lina->id])->assertSessionHasErrors('student_id');
});

test('a second plan on the same session does not take students from the first one', function () {
    $f = formationSetup();
    $lina = formationStudent('Lina');
    $first = sessionPlan($f, 1);
    $this->actingAs($f['admin'])->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['student_id' => $lina->id, 'training_plan_group_id' => $first->groups()->value('id')])->assertSessionHasNoErrors();

    sessionPlan($f, 1);

    expect(CourseEnrollment::where('student_id', $lina->id)->value('training_plan_group_id'))->toBe($first->groups()->value('id'));
});

test('session students without a group are placed when added from the plan, within group capacity', function () {
    $f = formationSetup();
    $students = collect(['Lina', 'Yanis', 'Sara'])->map(fn ($name) => formationStudent($name, null));
    foreach ($students as $student) {
        $this->actingAs($f['admin'])->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['student_id' => $student->id])->assertSessionHasNoErrors();
    }
    $plan = sessionPlan($f, 1);
    $group = $plan->groups()->firstOrFail();

    $this->post(route('admin.training-plans.groups.students.store', [$plan, $group]), ['student_ids' => $students->pluck('id')->all()])->assertSessionHasErrors('student_ids');
    $this->post(route('admin.training-plans.groups.students.store', [$plan, $group]), ['student_ids' => $students->take(2)->pluck('id')->all()])->assertSessionHasNoErrors();

    expect(CourseEnrollment::where('enrollment_form_id', $f['session']->id)->count())->toBe(3)
        ->and($group->enrollments()->count())->toBe(2);
});

test('a registered student can only be cancelled, then registered again', function () {
    $f = formationSetup();
    $lina = formationStudent('Lina');
    $this->actingAs($f['admin'])->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['student_id' => $lina->id])->assertSessionHasNoErrors();
    $enrollment = CourseEnrollment::where('student_id', $lina->id)->firstOrFail();

    $this->patch(route('admin.enrollment-forms.enrollments.status', [$f['session'], $enrollment]), ['status' => 'waiting'])->assertSessionHasErrors('status');
    $this->delete(route('admin.enrollment-forms.enrollments.destroy', [$f['session'], $enrollment]))->assertSessionHasNoErrors();
    expect($enrollment->fresh()->status)->toBe(ApplicationStatus::CANCELLED)
        ->and($enrollment->financialAccount()->value('closed_at'))->not->toBeNull();

    $this->post(route('admin.enrollment-forms.enrollments.store', $f['session']), ['student_id' => $lina->id])->assertSessionHasNoErrors();
    expect(CourseEnrollment::where('student_id', $lina->id)->where('status', 'registered')->count())->toBe(1);
});

test('groups with recorded attendance cannot be deleted but empty ones can', function () {
    $f = formationSetup();
    $plan = sessionPlan($f, 3);
    [$used, $empty] = $plan->groups()->orderBy('group_number')->take(2)->get()->all();
    $session = $used->sessions()->create(['classroom_id' => $f['room']->id, 'teacher_id' => $f['teacher']->id, 'title' => 'S1', 'starts_at' => today()->addDays(6)->setTime(9, 0), 'ends_at' => today()->addDays(6)->setTime(10, 0), 'attendance_status' => 'completed']);

    $this->actingAs($f['admin'])->delete(route('admin.training-plans.groups.destroy', [$plan, $used]))->assertSessionHasErrors('group');
    $this->delete(route('admin.training-plans.groups.destroy', [$plan, $empty]))->assertSessionHasNoErrors();
    expect($session->fresh())->not->toBeNull()->and($plan->groups()->count())->toBe(2);
});

test('cancelled sessions free their slot while a group cannot have two sessions at once', function () {
    $f = formationSetup();
    $plan = sessionPlan($f, 1);
    $group = $plan->groups()->firstOrFail();
    $otherTeacher = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::TEACHER]);
    $otherRoom = Classroom::create(['school_site_id' => $f['room']->school_site_id, 'name' => 'Salle 2', 'code' => 'R2', 'capacity' => 10, 'is_active' => true]);
    $slot = ['title' => 'S1', 'classroom_id' => $f['room']->id, 'teacher_id' => $f['teacher']->id, 'starts_at' => today()->addDays(6)->setTime(9, 0)->toDateTimeString(), 'ends_at' => today()->addDays(6)->setTime(10, 0)->toDateTimeString()];
    $this->actingAs($f['admin'])->post(route('admin.training-plans.sessions.store', [$plan, $group]), $slot)->assertSessionHasNoErrors();
    $this->post(route('admin.training-plans.sessions.store', [$plan, $group]), [...$slot, 'classroom_id' => $otherRoom->id, 'teacher_id' => $otherTeacher->id])->assertSessionHasErrors('starts_at');

    $session = $group->sessions()->firstOrFail();
    $this->delete(route('admin.training-plans.sessions.destroy', [$plan, $group, $session]))->assertSessionHasNoErrors();
    $this->post(route('admin.training-plans.sessions.store', [$plan, $group]), $slot)->assertSessionHasNoErrors();
});

test('completing a plan completes its enrollments and makes it read-only', function () {
    $f = formationSetup();
    $lina = formationStudent('Lina');
    $plan = sessionPlan($f, 1);
    $group = $plan->groups()->firstOrFail();
    $this->actingAs($f['admin'])->post(route('admin.training-plans.groups.students.store', [$plan, $group]), ['student_ids' => [$lina->id]])->assertSessionHasNoErrors();

    $this->put(route('admin.training-plans.update', $plan), ['title' => $plan->title, 'teacher_id' => $f['teacher']->id, 'status' => 'completed'])->assertSessionHasNoErrors();

    expect(CourseEnrollment::where('student_id', $lina->id)->value('status'))->toBe(ApplicationStatus::COMPLETED)
        ->and($group->enrollments()->count())->toBe(0);
    $this->post(route('admin.training-plans.sessions.store', [$plan, $group]), ['title' => 'Late', 'classroom_id' => $f['room']->id, 'teacher_id' => $f['teacher']->id, 'starts_at' => today()->addDays(7)->setTime(9, 0), 'ends_at' => today()->addDays(7)->setTime(10, 0)])->assertStatus(422);
});

test('teachers act on a plan only through their plan rights and staff roles through permissions', function () {
    $f = formationSetup();
    $plan = sessionPlan($f, 1);
    $group = $plan->groups()->firstOrFail();
    $slot = fn (int $day) => ['title' => "S{$day}", 'classroom_id' => $f['room']->id, 'teacher_id' => $f['teacher']->id, 'starts_at' => today()->addDays($day)->setTime(9, 0)->toDateTimeString(), 'ends_at' => today()->addDays($day)->setTime(10, 0)->toDateTimeString()];
    app(\App\Services\DefaultTenantRoles::class)->provision($f['tenant']);
    app(TenantRbac::class)->assignRolesWithScopes($f['teacher'], [['id' => Role::where('system_key', 'teacher')->value('id'), 'scope' => 'assigned']]);

    $this->actingAs($f['teacher'])->post(route('admin.training-plans.sessions.store', [$plan, $group]), $slot(6))->assertForbidden();
    $plan->teacherAccesses()->where('teacher_id', $f['teacher']->id)->update(['can_add_sessions' => true]);
    $this->post(route('admin.training-plans.sessions.store', [$plan, $group]), $slot(6))->assertRedirect()->assertSessionHasNoErrors();
    app(TenantContext::class)->set($f['tenant']);
    $session = $group->sessions()->firstOrFail();
    $this->put(route('admin.training-plans.sessions.update', [$plan, $group, $session]), $slot(8))->assertSessionHasNoErrors();
    app(TenantContext::class)->set($f['tenant']);
    $this->post(route('admin.training-plans.groups.students.store', [$plan, $group]), ['student_ids' => [formationStudent('Lina')->id]])->assertForbidden();

    app(TenantContext::class)->set($f['tenant']);
    $planner = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::EMPLOYEE]);
    $role = Role::create(['name' => 'Planning', 'is_active' => true]);
    app(TenantRbac::class)->syncPermissions($role, ['timetables.view', 'timetables.manage']);
    $planner->roles()->attach($role->id, ['tenant_id' => $f['tenant']->id, 'data_scope' => 'tenant']);
    $this->actingAs($planner)->post(route('admin.training-plans.sessions.store', [$plan, $group]), $slot(9))->assertSessionHasNoErrors();
});

test('public registration closes after the session ends and reopens for rejected applicants', function () {
    $f = formationSetup();
    $payload = ['first_name' => 'Lina', 'last_name' => 'Kaci', 'email' => 'lina@example.test', 'phone' => '0550000000'];
    app(TenantContext::class)->clear();
    \Illuminate\Support\Facades\Mail::fake();

    $this->post(route('public.enrollment.store', $f['session']->public_token), $payload)->assertSessionHasNoErrors();
    $application = CourseEnrollment::where('email', 'lina@example.test')->firstOrFail();
    $application->update(['confirmed_at' => now(), 'status' => ApplicationStatus::REJECTED]);
    $this->post(route('public.enrollment.store', $f['session']->public_token), $payload)->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe(ApplicationStatus::NEW);

    $f['session']->update(['end_date' => today()->subDay()]);
    $this->post(route('public.enrollment.store', $f['session']->public_token), [...$payload, 'first_name' => 'Yanis'])->assertStatus(422);
});
