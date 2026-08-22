<?php

use App\Enums\UserRole;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;

test('administrators can create staff without a login account', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $type = EmployeeType::where('slug', 'secretary')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'first_name' => 'Nadia', 'last_name' => 'Benali', 'employee_type_id' => $type->id,
        'employee_code' => 'EMP-SEC-001', 'email' => 'nadia.staff@example.test',
        'employment_status' => 'active', 'can_login' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $staff = Staff::where('employee_code', 'EMP-SEC-001')->firstOrFail();
    expect($staff->user_id)->toBeNull()->and($staff->employeeType->slug)->toBe('secretary');
});

test('teacher staff keeps a teacher user for planning compatibility', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $type = EmployeeType::where('slug', 'teacher')->firstOrFail();

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'first_name' => 'Amine', 'last_name' => 'Saadi', 'employee_type_id' => $type->id,
        'employee_code' => 'EMP-TEA-001', 'email' => 'amine.teacher@example.test',
        'employment_status' => 'active', 'can_login' => true,
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $staff = Staff::where('employee_code', 'EMP-TEA-001')->firstOrFail();
    expect($staff->user->role)->toBe(UserRole::TEACHER)->and($staff->is_teacher)->toBeTrue();
});

test('teachers cannot manage staff', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);
    $this->actingAs($teacher)->get(route('admin.staff.index'))->assertForbidden();
});

test('employee types are data driven and administrators can add custom types', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)->post(route('admin.staff.types.store'), [
        'name' => 'Bibliothécaire', 'is_teacher' => false,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_types', [
        'name' => 'Bibliothécaire', 'slug' => 'bibliothecaire', 'is_active' => true,
    ]);
});
