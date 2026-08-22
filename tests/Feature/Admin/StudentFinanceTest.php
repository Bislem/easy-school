<?php

use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Student;
use App\Models\User;

test('an inscription snapshots its formation price and accepts partial payments', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $course = Course::create(['title'=>'Anglais','code'=>'ANG-1','category'=>'Langues','duration_hours'=>20,'price'=>30000,'is_active'=>true]);
    $form = EnrollmentForm::create(['course_id'=>$course->id,'title'=>'Session anglais','start_date'=>today(),'end_date'=>today()->addMonth(),'min_students'=>1,'max_students'=>10,'groups_count'=>1,'students_per_group'=>10,'is_active'=>true]);
    $student = Student::create(['first_name'=>'Lina','last_name'=>'Kaci','phone'=>'0550000000','status'=>StudentStatus::ACTIVE,'is_active'=>true]);
    $enrollment = CourseEnrollment::create(['enrollment_form_id'=>$form->id,'student_id'=>$student->id,'status'=>ApplicationStatus::REGISTERED,'first_name'=>'Lina','last_name'=>'Kaci','email'=>'lina@example.test','phone'=>'0550000000','confirmation_token'=>str()->uuid(),'registered_at'=>now()]);

    expect((float)$enrollment->formation_price)->toBe(30000.0);
    $this->actingAs($admin)->post(route('admin.finance.payments.store',$enrollment), ['amount'=>10000,'payment_date'=>today()->toDateString(),'payment_method'=>'cash'])->assertSessionHasNoErrors();
    expect((float)$enrollment->refresh()->total_paid)->toBe(10000.0)->and((float)$enrollment->remaining_balance)->toBe(20000.0)->and($enrollment->payment_status->value)->toBe('partially_paid');
    expect(fn () => $enrollment->payments()->firstOrFail()->update(['amount'=>1]))->toThrow(\LogicException::class);
});
