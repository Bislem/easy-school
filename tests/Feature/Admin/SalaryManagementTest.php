<?php

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;

test('a salary is stored as an expense and visible in both modules', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['role' => UserRole::TEACHER, 'job_title' => 'Enseignant de français']);

    $this->actingAs($admin)->post(route('admin.salaries.store'), [
        'employee_id' => $teacher->id,
        'amount' => 65000,
        'salary_period' => '2026-08',
        'expense_date' => '2026-08-31',
        'payment_method' => 'bank_transfer',
        'reference' => 'SAL-2026-08-01',
        'notes' => 'Salaire mensuel.',
        'receipt_temp_folders' => [],
        'receipt_removed_files' => [],
    ])->assertSessionHasNoErrors();

    $salary = Expense::where('reference', 'SAL-2026-08-01')->firstOrFail();
    expect($salary->category)->toBe('Salaire')
        ->and($salary->employee_id)->toBe($teacher->id)
        ->and($salary->salary_period->format('Y-m'))->toBe('2026-08');

    $this->actingAs($admin)->get(route('admin.salaries.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.expenses.index'))->assertOk();
});

test('employees are non authenticable by default but access can be enabled', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Nadia Secrétaire',
        'email' => 'nadia@example.test',
        'phone' => '0550000000',
        'role' => 'employee',
        'job_title' => 'Secrétaire',
        'is_active' => true,
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasNoErrors();

    $employee = User::where('email', 'nadia@example.test')->firstOrFail();
    expect($employee->can_login)->toBeFalse()
        ->and($employee->role)->toBe(UserRole::EMPLOYEE);

    $employee->update(['password' => 'password']);
    $this->post(route('logout'));
    $this->post(route('login.store'), [
        'email' => 'nadia@example.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

test('teachers cannot manage salaries', function () {
    $teacher = User::factory()->create(['role' => UserRole::TEACHER]);

    $this->actingAs($teacher)->get(route('admin.salaries.index'))->assertForbidden();
});
