<?php

use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLevel;
use App\Models\EnrollmentForm;
use App\Models\Student;
use App\Models\TrainingPlan;
use App\Models\User;

test('an administrator can issue a manual certificate without an inscription or formation', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '-', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.certificates.store'), [
        'student_id' => $student->id, 'type' => 'success_certificate', 'issue_date' => '2026-08-24',
        'result' => 'Excellent', 'signature_name' => 'Direction',
    ])->assertSessionHasNoErrors();

    $certificate = Certificate::firstOrFail();
    expect($certificate->student_id)->toBe($student->id)
        ->and($certificate->course_enrollment_id)->toBeNull()
        ->and($certificate->formation_name)->toBeNull();
});

test('an administrator can explicitly include a warned student in bulk issuance', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Bureautique', 'code' => 'CERT-BULK', 'duration_hours' => 20, 'price' => 0, 'is_certified' => true, 'is_active' => true]);
    $level = CourseLevel::create(['course_id' => $course->id, 'name' => 'Niveau 1', 'code' => 'N1', 'duration_hours' => 20, 'price' => 0, 'is_active' => true]);
    $form = EnrollmentForm::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Session', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'min_students' => 1, 'max_students' => 10, 'groups_count' => 1, 'students_per_group' => 10, 'is_active' => true]);
    $plan = TrainingPlan::create(['course_level_id' => $level->id, 'enrollment_form_id' => $form->id, 'teacher_id' => $teacher->id, 'title' => 'Plan terminé', 'status' => 'completed']);
    $group = $plan->groups()->create(['group_number' => 1, 'name' => 'Groupe 1', 'capacity' => 10]);
    $student = Student::create(['first_name' => 'Samir', 'last_name' => 'Amrane', 'phone' => '-', 'status' => StudentStatus::SUSPENDED, 'is_active' => false]);
    $enrollment = CourseEnrollment::create(['enrollment_form_id' => $form->id, 'training_plan_group_id' => $group->id, 'student_id' => $student->id, 'status' => ApplicationStatus::REGISTERED, 'first_name' => 'Samir', 'last_name' => 'Amrane', 'email' => 'samir@example.test', 'phone' => '-', 'confirmation_token' => str()->uuid(), 'registered_at' => now(), 'group_number' => 1]);

    $this->actingAs($admin)->get(route('admin.certificates.index'))->assertOk();

    $this->actingAs($admin)->post(route('admin.certificates.bulk.store'), [
        'training_plan_id' => $plan->id, 'group_ids' => [$group->id], 'enrollment_ids' => [$enrollment->id],
        'type' => 'diploma', 'issue_date' => '2026-08-24',
    ])->assertSessionHasNoErrors();

    expect(Certificate::where('course_enrollment_id', $enrollment->id)->where('type', 'diploma')->exists())->toBeTrue();
});
