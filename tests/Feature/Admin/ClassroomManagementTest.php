<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;

test('administrators can view classrooms', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->get(route('admin.classrooms.index'))
        ->assertOk();
});

test('teachers cannot manage classrooms', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)
        ->get(route('admin.classrooms.index'))
        ->assertForbidden();
});

test('administrators can create and disable a classroom', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.classrooms.store'), [
            'name' => 'Salle informatique',
            'code' => 'INFO-01',
            'capacity' => 24,
            'location' => 'Premier étage',
            'description' => 'Salle équipée de postes informatiques.',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $classroom = Classroom::where('code', 'INFO-01')->firstOrFail();

    $this->actingAs($admin)
        ->patch(route('admin.classrooms.toggle-active', $classroom))
        ->assertSessionHasNoErrors();

    expect($classroom->refresh()->is_active)->toBeFalse();
});
