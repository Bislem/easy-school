<?php

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;

test('one school cannot access another schools student route', function () {
    $schoolA = Tenant::factory()->create();
    $schoolB = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $schoolA->id, 'role' => UserRole::ADMIN]);
    $student = new Student;
    $student->forceFill([
        'tenant_id' => $schoolB->id, 'first_name' => 'Private', 'last_name' => 'Student',
        'email' => 'private@student.test', 'phone' => '0550000000', 'is_active' => true,
    ])->save();

    $this->actingAs($admin)->get("/admin/students/{$student->id}")->assertNotFound();
    $this->actingAs($admin)->put("/admin/students/{$student->id}", [])->assertNotFound();
});

test('new records automatically belong to the authenticated school', function () {
    $school = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $school->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($admin)->post(route('admin.students.store'), [
        'first_name' => 'Lina', 'last_name' => 'Amrane', 'email' => 'lina@example.test',
        'phone' => '0550000000', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    expect(Student::withoutGlobalScopes()->where('email', 'lina@example.test')->value('tenant_id'))->toBe($school->id);
});
