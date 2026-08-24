<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\EnrollmentForm;
use App\Models\SchoolSite;
use App\Models\TrainingPlan;
use App\Models\User;

test('an administrator can create a plan from an enrollment form with all its groups', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Français', 'code' => 'FR-PLAN', 'duration_hours' => 20, 'price' => 10000, 'is_active' => true]);
    $level = CourseLevel::create(['course_id' => $course->id, 'name' => 'Débutant', 'code' => 'A1', 'duration_hours' => 20, 'price' => 10000, 'is_active' => true]);
    $form = EnrollmentForm::create([
        'course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Session français',
        'start_date' => '2026-09-01', 'end_date' => '2026-10-31', 'min_students' => 1,
        'max_students' => 30, 'groups_count' => 3, 'students_per_group' => 10, 'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('admin.training-plans.store'), [
        'source_type' => 'form', 'enrollment_form_id' => $form->id, 'course_level_id' => $level->id,
        'title' => 'Planification français', 'notes' => null,
    ])->assertSessionHasNoErrors();

    $plan = TrainingPlan::where('title', 'Planification français')->firstOrFail();
    expect($plan->groups()->count())->toBe(3)
        ->and($plan->course_level_id)->toBe($level->id)
        ->and($plan->teacher_id)->toBe($teacher->id);
});

test('room conflicts and excessive planned duration are rejected', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $otherTeacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Excel', 'code' => 'EXCEL-PLAN', 'duration_hours' => 4, 'price' => 5000, 'is_active' => true]);
    $site = SchoolSite::create(['name' => 'Site test', 'code' => 'SITE-PLAN', 'wilaya' => 'Béjaïa', 'is_active' => true]);
    $level = CourseLevel::create(['course_id' => $course->id, 'name' => 'Intermédiaire', 'code' => 'N2', 'duration_hours' => 4, 'price' => 5000, 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => 'Salle A', 'code' => 'SA-PLAN', 'capacity' => 20, 'is_active' => true]);
    $otherRoom = Classroom::create(['school_site_id' => $site->id, 'name' => 'Salle B', 'code' => 'SB-PLAN', 'capacity' => 20, 'is_active' => true]);
    $plan = TrainingPlan::create(['course_level_id' => $level->id, 'teacher_id' => $teacher->id, 'title' => 'Plan Excel', 'status' => 'draft']);
    $groupOne = $plan->groups()->create(['group_number' => 1, 'name' => 'Groupe 1', 'classroom_id' => $room->id, 'capacity' => 20]);
    $groupTwo = $plan->groups()->create(['group_number' => 2, 'name' => 'Groupe 2', 'classroom_id' => $room->id, 'capacity' => 20]);

    $this->actingAs($admin)->post(route('admin.training-plans.sessions.store', [$plan, $groupOne]), [
        'title' => 'Séance 1', 'classroom_id' => $room->id, 'teacher_id' => $teacher->id,
        'starts_at' => '2026-09-10 08:00:00', 'ends_at' => '2026-09-10 11:00:00', 'notes' => null,
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post(route('admin.training-plans.sessions.store', [$plan, $groupTwo]), [
        'title' => 'Conflit salle', 'classroom_id' => $room->id, 'teacher_id' => $otherTeacher->id,
        'starts_at' => '2026-09-10 09:00:00', 'ends_at' => '2026-09-10 10:00:00', 'notes' => null,
    ])->assertSessionHasErrors('classroom_id');

    $this->actingAs($admin)->post(route('admin.training-plans.sessions.store', [$plan, $groupOne]), [
        'title' => 'Durée excessive', 'classroom_id' => $otherRoom->id, 'teacher_id' => $teacher->id,
        'starts_at' => '2026-09-11 08:00:00', 'ends_at' => '2026-09-11 10:00:00', 'notes' => null,
    ])->assertSessionHasErrors('ends_at');

    expect($groupOne->sessions()->count())->toBe(1);
});

test('teachers cannot manage planifications', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->actingAs($teacher)->get(route('admin.training-plans.index'))->assertForbidden();
});
