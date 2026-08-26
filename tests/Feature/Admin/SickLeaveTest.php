<?php

use App\Enums\UserRole;
use App\Models\EmployeeType;
use App\Models\SickLeave;
use App\Models\Staff;
use App\Models\User;

function sickLeaveStaff(): array
{
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $type = EmployeeType::create(['name' => 'Administration', 'slug' => 'administration', 'is_active' => true]);
    $staff = Staff::create(['user_id' => $employee->id, 'employee_type_id' => $type->id, 'first_name' => 'Lina', 'last_name' => 'Test', 'employee_code' => 'EMP-SICK', 'email' => $employee->email, 'hire_date' => '2025-01-01', 'employment_status' => 'active', 'social_security_number' => 'SS-100']);

    return [$admin, $staff];
}

test('sick leave has an audited validation and return workflow', function () {
    [$admin,$staff] = sickLeaveStaff();
    $this->actingAs($admin)->post(route('admin.staff.sick-leaves.store', $staff), ['category' => 'hospitalization', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-05', 'certificate_received' => true, 'certificate_reference' => 'CERT-01', 'certificate_issued_at' => '2026-09-01', 'health_professional' => 'Hôpital central'])->assertSessionHasNoErrors();
    $leave = SickLeave::firstOrFail();
    expect($leave->days)->toBe(5)->and($leave->status)->toBe('pending')->and($leave->events)->toHaveCount(1);
    $this->actingAs($admin)->patch(route('admin.staff.sick-leaves.approve', [$staff, $leave]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('admin.staff.sick-leaves.complete', [$staff, $leave]), ['actual_return_date' => '2026-09-06', 'fit_to_return_confirmed' => true])->assertSessionHasNoErrors();
    expect($leave->refresh()->status)->toBe('taken')->and($leave->events)->toHaveCount(3);
    foreach (['declaration', 'decision', 'return'] as $document) {
        $this->actingAs($admin)->get(route('admin.staff.sick-leaves.print', [$staff, $leave]).'?document='.$document)->assertOk()->assertHeader('content-type', 'application/pdf');
    }
});

test('overlapping active leave is rejected', function () {
    [$admin,$staff] = sickLeaveStaff();
    $staff->annualLeaves()->create(['mode' => 'days', 'starts_at' => '2026-10-01', 'ends_at' => '2026-10-10', 'days' => 10, 'status' => 'approved']);
    $this->actingAs($admin)->post(route('admin.staff.sick-leaves.store', $staff), ['category' => 'illness', 'starts_at' => '2026-10-05', 'ends_at' => '2026-10-07', 'certificate_received' => false])->assertSessionHasErrors('starts_at');
});
