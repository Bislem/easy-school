<?php

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\SchoolSite;
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
    $site = SchoolSite::create(['name' => 'Site Béjaïa', 'code' => 'BEJ', 'wilaya' => 'Béjaïa', 'is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.classrooms.store'), [
            'name' => 'Salle informatique',
            'school_site_id' => $site->id,
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

test('administrators can manage school sites', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $this->actingAs($admin)->post(route('admin.sites.store'), [
        'name' => 'Annexe Alger', 'code' => 'ALG', 'wilaya' => 'Alger',
        'commune' => 'Hydra', 'address' => null, 'phone' => null, 'is_active' => true,
    ])->assertSessionHasNoErrors();
    expect(SchoolSite::where('code', 'ALG')->exists())->toBeTrue();
});
