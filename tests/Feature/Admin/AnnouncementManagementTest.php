<?php

use App\Enums\UserRole;
use App\Models\SchoolAnnouncement;
use App\Models\User;

test('administrator can save and publish an announcement without a poster', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
        'title' => 'Réunion des parents',
        'message' => 'Une réunion est programmée vendredi.',
        'delivery' => 'account',
        'target' => 'all',
        'status' => 'published',
        'cycle_ids' => [],
        'poster_temp_folders' => [],
    ]);

    $response->assertSessionHasNoErrors();
    expect(SchoolAnnouncement::where('title', 'Réunion des parents')->firstOrFail()->status)->toBe('published');
});
