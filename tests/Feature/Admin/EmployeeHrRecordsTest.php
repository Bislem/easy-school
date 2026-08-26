<?php

use App\Enums\UserRole;
use App\Models\EmployeeHrRecord;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;

test('administrators can manage auditable records for every remaining HR section', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
    $type = EmployeeType::create(['name' => 'RH', 'slug' => 'hr', 'is_active' => true]);
    $staff = Staff::create(['user_id' => $employee->id, 'employee_type_id' => $type->id, 'first_name' => 'Samir', 'last_name' => 'Test', 'employee_code' => 'EMP-HR', 'email' => $employee->email, 'employment_status' => 'active']);
    foreach (['absence' => 'personal', 'contract' => 'cdi', 'training' => 'internal', 'evaluation' => 'annual', 'discipline' => 'written_warning', 'note' => 'general'] as $category => $recordType) {
        $definition = config("hr.record_categories.$category");
        $this->actingAs($admin)->post(route('admin.staff.hr-records.store', $staff), ['category' => $category, 'type' => $recordType, 'title' => "Test $category", 'status' => array_key_first($definition['statuses']), 'is_confidential' => in_array($category, ['evaluation', 'discipline', 'note'])])->assertSessionHasNoErrors();
    }
    expect(EmployeeHrRecord::count())->toBe(6)->and(EmployeeHrRecord::withCount('events')->get()->every(fn ($record) => $record->events_count === 1))->toBeTrue();
    $record = EmployeeHrRecord::where('category', 'training')->firstOrFail();
    $this->actingAs($admin)->patch(route('admin.staff.hr-records.status', [$staff, $record]), ['status' => 'completed'])->assertSessionHasNoErrors();
    expect($record->refresh()->status)->toBe('completed')->and($record->events)->toHaveCount(2);
    $this->actingAs($admin)->patch(route('admin.staff.hr-records.archive', [$staff, $record]))->assertSessionHasNoErrors();
    expect($record->refresh()->archived_at)->not->toBeNull();
});
