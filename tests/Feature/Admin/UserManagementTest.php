<?php

use App\Enums\UserRole;
use App\Models\User;

test('administrators can view user management', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();
});

test('teachers cannot view user management', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('administrators can create a teacher', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Nouvel Enseignant',
            'email' => 'nouvel.enseignant@example.com',
            'phone' => '0550000000',
            'birth_date' => '1990-01-01',
            'role' => UserRole::TEACHER->value,
            'is_active' => true,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'nouvel.enseignant@example.com',
        'role' => UserRole::TEACHER->value,
        'is_active' => true,
    ]);
});

test('administrators can disable another user but not themselves', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER, 'is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.users.toggle-active', $teacher))
        ->assertSessionHasNoErrors();

    expect($teacher->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.users.toggle-active', $admin))
        ->assertSessionHasErrors('user');

    expect($admin->refresh()->is_active)->toBeTrue();
});
