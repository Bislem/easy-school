<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

test('administrators can create and manage student records', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.students.store'), [
            'first_name' => 'Amel',
            'last_name' => 'Rahmani',
            'email' => 'amel.rahmani@example.com',
            'phone' => '0550000000',
            'birth_date' => '2003-05-12',
            'address' => 'Béjaïa',
            'notes' => 'Dossier complet.',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $student = Student::where('email', 'amel.rahmani@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.students.toggle-active', $student))
        ->assertSessionHasNoErrors();

    expect($student->refresh()->is_active)->toBeFalse();
    $this->assertDatabaseMissing('users', ['email' => 'amel.rahmani@example.com']);
});

test('teachers cannot manage student records', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)
        ->get(route('admin.students.index'))
        ->assertForbidden();
});
