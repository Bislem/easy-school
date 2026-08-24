<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\User;

test('administrators can manage the course catalogue', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->post(route('admin.courses.store'), [
            'title' => 'Développement Web Full Stack',
            'code' => 'DEV-WEB',
            'category' => 'Informatique',
            'duration_hours' => 120,
            'price' => 45000,
            'description' => 'Formation pratique en développement web.',
            'objectives' => 'Créer des applications web complètes.',
            'prerequisites' => 'Notions de base en informatique.',
            'is_certified' => true,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $course = Course::where('code', 'DEV-WEB')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.courses.levels.store', $course), [
        'name' => 'Débutant', 'code' => 'N1', 'duration_hours' => 40,
        'price' => 15000, 'prerequisites' => null, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(CourseLevel::whereBelongsTo($course)->firstOrFail()->name)->toBe('Débutant');

    $this->actingAs($admin)
        ->patch(route('admin.courses.toggle-active', $course))
        ->assertSessionHasNoErrors();

    expect($course->refresh()->is_active)->toBeFalse();
});

test('teachers cannot manage the course catalogue', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)
        ->get(route('admin.courses.index'))
        ->assertForbidden();
});
