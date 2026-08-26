<?php

use App\Enums\UserRole;
use App\Models\AnnualLeave;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;
use App\Services\AnnualLeaveService;
use Illuminate\Support\Carbon;

function annualLeaveStaff(string $hireDate = '2025-01-15'): array
{
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $type = EmployeeType::create(['name' => 'Administration', 'slug' => 'administration', 'is_active' => true]);
    $staff = Staff::create(['user_id' => $employee->id, 'employee_type_id' => $type->id, 'first_name' => 'Nadia', 'last_name' => 'Test', 'employee_code' => 'EMP-LEAVE', 'email' => $employee->email, 'hire_date' => $hireDate, 'employment_status' => 'active']);

    return [$admin, $staff];
}

test('annual leave accrues 2.5 days for each completed month', function () {
    [, $staff] = annualLeaveStaff('2026-01-15');
    $summary = app(AnnualLeaveService::class)->summary($staff, Carbon::parse('2026-08-14'));
    expect($summary['completed_months'])->toBe(6)->and($summary['accrued'])->toBe(15.0);

    $summary = app(AnnualLeaveService::class)->summary($staff, Carbon::parse('2026-08-15'));
    expect($summary['completed_months'])->toBe(7)->and($summary['accrued'])->toBe(17.5);
});

test('an imported balance becomes the accrual baseline without deducting older history twice', function () {
    [, $staff] = annualLeaveStaff('2020-01-01');
    $staff->update(['leave_opening_balance' => 12.5, 'leave_balance_as_of' => '2026-07-01', 'leave_balance_note' => 'Reprise ancien registre']);
    $staff->annualLeaves()->create(['mode' => 'days', 'starts_at' => '2026-06-01', 'ends_at' => '2026-06-05', 'days' => 5, 'status' => 'taken']);
    $staff->annualLeaves()->create(['mode' => 'days', 'starts_at' => '2026-08-10', 'ends_at' => '2026-08-11', 'days' => 2, 'status' => 'approved']);

    $summary = app(AnnualLeaveService::class)->summary($staff->refresh(), Carbon::parse('2026-09-01'));
    expect($summary['opening_balance'])->toBe(12.5)
        ->and($summary['earned_since_baseline'])->toBe(5.0)
        ->and($summary['used'])->toBe(2.0)
        ->and($summary['available'])->toBe(15.5);
});

test('an administrator can request approve and complete annual leave with audit history', function () {
    [$admin, $staff] = annualLeaveStaff();
    Carbon::setTestNow('2026-08-26 10:00:00');

    $this->actingAs($admin)->post(route('admin.staff.annual-leaves.store', $staff), [
        'mode' => 'days', 'starts_at' => '2026-09-01', 'ends_at' => '2026-09-05', 'reason' => 'Repos annuel',
    ])->assertSessionHasNoErrors();

    $leave = AnnualLeave::firstOrFail();
    expect((float) $leave->days)->toBe(5.0)->and($leave->status)->toBe('pending')->and($leave->events)->toHaveCount(1);
    expect(app(AnnualLeaveService::class)->summary($staff)['pending'])->toBe(5.0);

    $this->actingAs($admin)->patch(route('admin.staff.annual-leaves.approve', [$staff, $leave]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->patch(route('admin.staff.annual-leaves.complete', [$staff, $leave]), ['actual_return_date' => '2026-09-06'])->assertSessionHasNoErrors();

    expect($leave->refresh()->status)->toBe('taken')->and($leave->actual_return_date->format('Y-m-d'))->toBe('2026-09-06')->and($leave->events)->toHaveCount(3);
    foreach (['request', 'authorization', 'return'] as $document) {
        $this->actingAs($admin)->get(route('admin.staff.annual-leaves.print', [$staff, $leave]).'?document='.$document)
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
    Carbon::setTestNow();
});

test('a full month costs 30 days and overlapping or over balance requests are rejected', function () {
    [$admin, $staff] = annualLeaveStaff('2024-01-01');
    Carbon::setTestNow('2026-08-26 10:00:00');
    $this->actingAs($admin)->post(route('admin.staff.annual-leaves.store', $staff), ['mode' => 'full_month', 'month' => '2026-10'])->assertSessionHasNoErrors();
    $leave = AnnualLeave::firstOrFail();
    expect((float) $leave->days)->toBe(30.0)->and($leave->starts_at->format('Y-m-d'))->toBe('2026-10-01')->and($leave->ends_at->format('Y-m-d'))->toBe('2026-10-31');

    $this->actingAs($admin)->post(route('admin.staff.annual-leaves.store', $staff), ['mode' => 'days', 'starts_at' => '2026-10-20', 'ends_at' => '2026-10-21'])->assertSessionHasErrors('starts_at');
    $this->actingAs($admin)->post(route('admin.staff.annual-leaves.store', $staff), ['mode' => 'days', 'starts_at' => '2026-11-01', 'ends_at' => '2027-12-31'])->assertSessionHasErrors('days');
    Carbon::setTestNow();
});
