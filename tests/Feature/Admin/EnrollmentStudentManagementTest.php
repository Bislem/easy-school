<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\EnrollmentForm;
use App\Models\Student;
use App\Models\User;

test('an administrator can add and remove a student from a form', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Formation', 'code' => 'FORM', 'duration_hours' => 20, 'price' => 0, 'is_certified' => false, 'is_active' => true]);
    $form = EnrollmentForm::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Inscriptions', 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2), 'min_students' => 1, 'max_students' => 10, 'groups_count' => 2, 'students_per_group' => 5, 'is_active' => true]);

    $this->actingAs($admin)->post(route('admin.enrollment-forms.enrollments.store', $form), [
        'first_name' => 'Lina', 'last_name' => 'Kaci', 'email' => 'lina@example.com',
        'phone' => '0550000000', 'group_number' => 2,
    ])->assertSessionHasNoErrors();

    $enrollment = $form->enrollments()->firstOrFail();
    expect($enrollment->confirmed_at)->not->toBeNull()
        ->and($enrollment->group_number)->toBe(2)
        ->and(Student::where('email', 'lina@example.com')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->delete(route('admin.enrollment-forms.enrollments.destroy', [$form, $enrollment]))
        ->assertSessionHasNoErrors();

    expect($form->enrollments()->count())->toBe(0)
        ->and(Student::where('email', 'lina@example.com')->exists())->toBeTrue();
});
