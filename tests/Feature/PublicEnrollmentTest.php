<?php

use App\Enums\UserRole;
use App\Mail\EnrollmentConfirmationMail;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

test('a public enrollment requires email confirmation before creating a student', function () {
    Mail::fake();
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Formation test', 'code' => 'TEST', 'duration_hours' => 10, 'price' => 0, 'is_certified' => false, 'is_active' => true]);
    $form = EnrollmentForm::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Inscriptions test', 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2), 'min_students' => 1, 'max_students' => 10, 'groups_count' => 2, 'students_per_group' => 5, 'is_active' => true]);

    $this->post(route('public.enrollment.store', $form->public_token), [
        'first_name' => 'Amel', 'last_name' => 'Rahmani', 'email' => 'AMEL@example.com', 'phone' => '0550000000',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('students', ['email' => 'amel@example.com']);
    Mail::assertSent(EnrollmentConfirmationMail::class);

    $enrollment = CourseEnrollment::firstOrFail();
    $url = URL::temporarySignedRoute('public.enrollment.confirm', now()->addHour(), ['enrollment' => $enrollment, 'token' => $enrollment->confirmation_token]);
    $this->get($url)->assertOk();

    $this->assertDatabaseHas('students', ['email' => 'amel@example.com']);
    expect($enrollment->refresh()->group_number)->toBe(1);
});

test('an existing student is reused when confirming another formation', function () {
    Mail::fake();
    $student = Student::create(['first_name' => 'Amel', 'last_name' => 'Rahmani', 'email' => 'amel@example.com', 'phone' => '0550000000', 'is_active' => true]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $course = Course::create(['title' => 'Autre formation', 'code' => 'AUTRE', 'duration_hours' => 10, 'price' => 0, 'is_certified' => false, 'is_active' => true]);
    $form = EnrollmentForm::create(['course_id' => $course->id, 'teacher_id' => $teacher->id, 'title' => 'Autre inscription', 'start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2), 'min_students' => 1, 'max_students' => 10, 'groups_count' => 1, 'students_per_group' => 10, 'is_active' => true]);
    $enrollment = CourseEnrollment::create(['enrollment_form_id' => $form->id, 'first_name' => 'Amel', 'last_name' => 'Rahmani', 'email' => 'amel@example.com', 'phone' => '0550000000', 'confirmation_token' => fake()->uuid()]);
    $url = URL::temporarySignedRoute('public.enrollment.confirm', now()->addHour(), ['enrollment' => $enrollment, 'token' => $enrollment->confirmation_token]);

    $this->get($url)->assertOk();

    expect(Student::where('email', 'amel@example.com')->count())->toBe(1)
        ->and($enrollment->refresh()->student_id)->toBe($student->id);
});
