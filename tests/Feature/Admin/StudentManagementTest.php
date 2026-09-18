<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('administrators can search students by full name, parent contact, enrollment data, and id', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)->post(route('admin.students.store'), [
        'first_name' => 'Yasmine',
        'last_name' => 'Chettout',
        'email' => 'yasmine.search@example.com',
        'phone' => '0551111111',
        'parent_phone' => '0662222222',
        'is_active' => true,
    ])->assertSessionHasNoErrors();
    $student = Student::where('email', 'yasmine.search@example.com')->firstOrFail();

    $this->actingAs($admin)->get(route('admin.students.index', ['search' => 'Chettout Yasmine']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('students.data.0.id', $student->id));
    $this->actingAs($admin)->get(route('admin.students.index', ['search' => '0662222222']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('students.data.0.id', $student->id));
    $this->actingAs($admin)->get(route('admin.students.index', ['search' => (string) $student->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('students.data.0.id', $student->id));
});
