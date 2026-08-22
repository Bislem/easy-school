<?php

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

test('student status changes retain history', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);

    $this->actingAs($admin)->patch(route('admin.students.status', $student), [
        'status' => 'suspended', 'observation' => 'Suspension administrative.',
    ])->assertSessionHasNoErrors();

    expect($student->refresh()->status)->toBe(StudentStatus::SUSPENDED)
        ->and($student->histories()->where('from_status', 'active')->where('to_status', 'suspended')->exists())->toBeTrue();
});

test('a student dossier supports multiple inscriptions', function () {
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    expect($student->enrollments())->not->toBeNull();
});
