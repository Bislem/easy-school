<?php

use App\Enums\UserRole;
use App\Models\EmployeeDocument;
use App\Models\EmployeeType;
use App\Models\SalaryConfiguration;
use App\Models\SalaryStatement;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use MohamedGaldi\ViltFilepond\Models\TempFile;

test('an administrator can open an employee HR folder with payroll history', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $type = EmployeeType::create(['name' => 'Administration', 'slug' => 'administration', 'is_active' => true]);
    $staff = Staff::create([
        'user_id' => $employee->id,
        'employee_type_id' => $type->id,
        'first_name' => 'Amine',
        'last_name' => 'Test',
        'employee_code' => 'EMP-TEST',
        'email' => $employee->email,
        'employment_status' => 'active',
    ]);
    $configuration = SalaryConfiguration::create([
        'name' => 'Salaire mensuel',
        'salary_type' => 'monthly',
        'base_rate' => 50000,
        'effective_from' => '2026-01-01',
    ]);
    SalaryStatement::create([
        'staff_id' => $staff->id,
        'salary_configuration_id' => $configuration->id,
        'reference' => 'SAL-TEST',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'salary_type' => 'monthly',
        'base_rate' => 50000,
        'units' => 1,
        'gross_salary' => 50000,
        'net_salary' => 50000,
        'remaining_amount' => 50000,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)->get(route('admin.staff.show', $staff))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Staff/Show')
            ->where('employee.id', $staff->id)
            ->where('employee.salary_configurations.0.id', $configuration->id));
});

test('an administrator can add and remove categorized employee documents', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $type = EmployeeType::create(['name' => 'Administration', 'slug' => 'administration', 'is_active' => true]);
    $staff = Staff::create([
        'user_id' => $employee->id, 'employee_type_id' => $type->id,
        'first_name' => 'Sara', 'last_name' => 'Test', 'employee_code' => 'EMP-DOC',
        'email' => $employee->email, 'employment_status' => 'active',
    ]);
    Storage::disk('public')->put('temp-files/folder-one/contract.pdf', 'document content');
    TempFile::create([
        'folder' => 'folder-one', 'original_name' => 'contrat.pdf', 'filename' => 'contract.pdf',
        'path' => 'temp-files/folder-one/contract.pdf', 'mime_type' => 'application/pdf', 'size' => 16,
    ]);

    $this->actingAs($admin)->post(route('admin.staff.documents.store', $staff), [
        'temp_folders' => ['folder-one'], 'type' => 'contract', 'title' => 'Contrat CDI',
        'reference' => 'CTR-2026-01', 'issued_at' => '2026-01-01', 'expires_at' => '2027-01-01',
    ])->assertSessionHasNoErrors();

    $document = EmployeeDocument::firstOrFail();
    expect($document->staff_id)->toBe($staff->id)
        ->and($document->type)->toBe('contract')
        ->and($document->file)->not->toBeNull();

    $this->actingAs($admin)->delete(route('admin.staff.documents.destroy', [$staff, $document]))
        ->assertSessionHasNoErrors();
    expect(EmployeeDocument::find($document->id))->toBeNull();
});
