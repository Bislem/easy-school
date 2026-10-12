<?php

use App\Enums\StudentAcademicEnrollmentStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\FinancialAccount;
use App\Models\SchoolCycle;
use App\Models\SchoolFeeStructure;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

function schoolBillingFixture(): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $cycle = SchoolCycle::create(['name' => 'Primaire', 'code' => 'PRI', 'sort_order' => 1, 'is_active' => true]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '3e année', 'code' => '3AP', 'sort_order' => 1, 'is_active' => true]);
    $group = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'name' => '3AP-A', 'code' => '3AP-A', 'capacity' => 30, 'is_active' => true]);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    $enrollment = StudentAcademicEnrollment::create(['academic_year_id' => $year->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'school_group_id' => $group->id, 'status' => StudentAcademicEnrollmentStatus::ENROLLED, 'enrollment_date' => '2026-09-01']);

    return compact('tenant', 'admin', 'year', 'level', 'group', 'student', 'enrollment');
}

test('school fees are generated idempotently and configurable', function () {
    $f = schoolBillingFixture();
    $structure = SchoolFeeStructure::create(['academic_year_id' => $f['year']->id, 'school_level_id' => $f['level']->id, 'name' => 'Scolarité annuelle', 'is_active' => true]);
    $component = $structure->components()->create(['code' => 'tuition', 'label' => 'Scolarité', 'amount' => 120000, 'is_mandatory' => true, 'sort_order' => 1]);
    foreach ([['Inscription', 10000, '2026-09-01'], ['Trimestre 1', 40000, '2026-10-01'], ['Trimestre 2', 35000, '2027-01-05'], ['Trimestre 3', 35000, '2027-04-05']] as $i => $row) {
        $structure->scheduleItems()->create(['school_fee_component_id' => $component->id, 'label' => $row[0], 'amount' => $row[1], 'due_date' => $row[2], 'sort_order' => $i]);
    }
    $payload = ['academic_year_id' => $f['year']->id, 'level_id' => $f['level']->id, 'group_id' => $f['group']->id, 'school_fee_structure_id' => $structure->id, 'update_existing' => false];
    $this->actingAs($f['admin'])->post(route('admin.school-payments.generate'), $payload)->assertSessionHasNoErrors();
    $this->post(route('admin.school-payments.generate'), $payload)->assertSessionHasNoErrors();
    $account = FinancialAccount::where('student_id', $f['student']->id)->firstOrFail();
    expect($account->installments)->toHaveCount(4)->and((float) $account->expected_total)->toBe(120000.0)->and(FinancialAccount::count())->toBe(1);
});

test('school payments support partial fifo allocation and immutable history', function () {
    $f = schoolBillingFixture();
    $account = FinancialAccount::create(['accountable_type' => $f['enrollment']->getMorphClass(), 'accountable_id' => $f['enrollment']->id, 'domain' => 'school', 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id]);
    $account->installments()->create(['source_key' => 'one', 'label' => 'Trimestre 1', 'amount' => 40000, 'due_date' => today()->addDay(), 'sort_order' => 1]);
    $account->installments()->create(['source_key' => 'two', 'label' => 'Trimestre 2', 'amount' => 35000, 'due_date' => today()->addMonth(), 'sort_order' => 2]);
    app(\App\Services\FinancialAccountService::class)->refresh($account);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.payments.store', $account), ['amount' => 50000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    $account->refresh();
    $transaction = $account->transactions()->firstOrFail();
    expect((float) $account->paid_total)->toBe(50000.0)->and((float) $account->balance)->toBe(25000.0)->and($transaction->allocations)->toHaveCount(2)->and(fn () => $transaction->update(['amount' => 1]))->toThrow(LogicException::class);
});

test('school financial accounts are tenant isolated', function () {
    $first = schoolBillingFixture();
    $account = FinancialAccount::create(['accountable_type' => $first['enrollment']->getMorphClass(), 'accountable_id' => $first['enrollment']->id, 'domain' => 'school', 'student_id' => $first['student']->id, 'academic_year_id' => $first['year']->id]);
    $secondTenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($secondTenant);
    $secondAdmin = User::factory()->create(['tenant_id' => $secondTenant->id, 'role' => UserRole::ADMIN]);
    $this->actingAs($secondAdmin)->get(route('admin.school-payments.accounts.show', $account->id))->assertNotFound();
});

test('a level specific structure automatically targets its specialization', function () {
    $f = schoolBillingFixture();
    $otherLevel = SchoolLevel::create(['school_cycle_id' => $f['level']->school_cycle_id, 'name' => '2AS', 'code' => '2AS', 'specialization' => 'Mathématiques', 'sort_order' => 20, 'is_active' => true]);
    $otherGroup = SchoolGroup::create(['academic_year_id' => $f['year']->id, 'school_level_id' => $otherLevel->id, 'name' => '2AS-MATH-A', 'code' => '2AS-MATH-A', 'capacity' => 30, 'is_active' => true]);
    $otherStudent = Student::create(['first_name' => 'Amine', 'last_name' => 'Math', 'phone' => '0550000001', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    $otherEnrollment = StudentAcademicEnrollment::create(['academic_year_id' => $f['year']->id, 'student_id' => $otherStudent->id, 'school_level_id' => $otherLevel->id, 'school_group_id' => $otherGroup->id, 'status' => StudentAcademicEnrollmentStatus::ENROLLED, 'enrollment_date' => '2026-09-01']);
    $structure = SchoolFeeStructure::create(['academic_year_id' => $f['year']->id, 'school_level_id' => $f['level']->id, 'name' => 'Tarif 3AP', 'is_active' => true]);
    $component = $structure->components()->create(['code' => 'tuition', 'label' => 'Scolarité', 'amount' => 50000, 'is_mandatory' => true, 'sort_order' => 0]);
    $structure->scheduleItems()->create(['school_fee_component_id' => $component->id, 'label' => 'Scolarité', 'amount' => 50000, 'due_date' => '2026-10-01', 'sort_order' => 0]);

    $this->actingAs($f['admin'])->post(route('admin.school-payments.generate'), [
        'academic_year_id' => $f['year']->id,
        'school_fee_structure_id' => $structure->id,
        'update_existing' => false,
    ])->assertSessionHasNoErrors();

    expect($f['enrollment']->financialAccount()->exists())->toBeTrue()
        ->and($otherEnrollment->financialAccount()->exists())->toBeFalse();
});

function schoolLedgerAccount(array $f, array $installments): FinancialAccount
{
    $account = FinancialAccount::create(['accountable_type' => $f['enrollment']->getMorphClass(), 'accountable_id' => $f['enrollment']->id, 'domain' => 'school', 'student_id' => $f['student']->id, 'academic_year_id' => $f['year']->id]);
    foreach ($installments as $i => [$label, $amount, $due]) {
        $account->installments()->create(['source_key' => "item-{$i}", 'label' => $label, 'amount' => $amount, 'due_date' => $due, 'sort_order' => $i]);
    }

    return app(\App\Services\FinancialAccountService::class)->refresh($account);
}

function schoolStructure(array $f, array $attributes, float $amount = 50000): SchoolFeeStructure
{
    $structure = SchoolFeeStructure::create(['academic_year_id' => $f['year']->id, 'name' => 'Tarif', 'is_active' => true, ...$attributes]);
    $component = $structure->components()->create(['code' => 'tuition', 'label' => 'Scolarité', 'amount' => $amount, 'is_mandatory' => true, 'sort_order' => 0]);
    $structure->scheduleItems()->create(['school_fee_component_id' => $component->id, 'label' => 'Scolarité', 'amount' => $amount, 'due_date' => today()->addMonth(), 'sort_order' => 0]);

    return $structure;
}

test('a discount settles the latest installments so a full payment closes every installment', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 30000, today()->addDay()], ['T2', 30000, today()->addMonth()], ['T3', 30000, today()->addMonths(2)]]);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.movements.store', $account), ['type' => 'discount', 'amount' => 10000, 'reason' => 'Fratrie'])->assertSessionHasNoErrors();
    $this->post(route('admin.school-payments.payments.store', $account), ['amount' => 80000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();

    $account->refresh();
    expect((float) $account->balance)->toBe(0.0)
        ->and($account->status)->toBe('paid')
        ->and((float) $account->paid_total)->toBe(80000.0)
        ->and((float) $account->adjustment_total)->toBe(-10000.0)
        ->and($account->installments()->pluck('status')->unique()->all())->toBe(['paid']);
});

test('a discount cannot exceed the remaining balance', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 30000, today()->addDay()]]);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.movements.store', $account), ['type' => 'scholarship', 'amount' => 30000.01, 'reason' => 'Trop'])->assertSessionHasErrors('amount');
});

test('cancelling a payment restores the balance and keeps both entries', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 40000, today()->subDay()], ['T2', 35000, today()->addMonth()]]);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.payments.store', $account), ['amount' => 50000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    $payment = $account->transactions()->where('type', 'payment')->firstOrFail();

    $this->post(route('admin.school-payments.transactions.cancel', $payment), ['reason' => 'Erreur de saisie'])->assertSessionHasNoErrors();
    $this->post(route('admin.school-payments.transactions.cancel', $payment), ['reason' => 'Encore'])->assertSessionHasErrors('transaction');

    $account->refresh();
    expect((float) $account->balance)->toBe(75000.0)
        ->and((float) $account->paid_total)->toBe(0.0)
        ->and($account->status)->toBe('overdue')
        ->and($account->transactions()->count())->toBe(2)
        ->and($account->transactions()->where('type', 'reversal')->value('reverses_transaction_id'))->toBe($payment->id);
});

test('a debit adjustment adds an installment that payments can settle', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 10000, today()->addDay()]]);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.movements.store', $account), ['type' => 'adjustment', 'amount' => 2500, 'reason' => 'Tenue', 'due_date' => today()->addWeek()->toDateString()])->assertSessionHasNoErrors();
    $this->post(route('admin.school-payments.payments.store', $account), ['amount' => 12500, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();

    $account->refresh();
    $payment = $account->transactions()->where('type', 'payment')->firstOrFail();
    expect($account->installments)->toHaveCount(2)
        ->and((float) $account->expected_total)->toBe(12500.0)
        ->and((float) $account->balance)->toBe(0.0)
        ->and((float) $payment->allocations()->sum('amount'))->toBe(12500.0);
});

test('a student specific structure only targets that student and wins over the level structure', function () {
    $f = schoolBillingFixture();
    $sibling = Student::create(['first_name' => 'Yanis', 'last_name' => 'Kaci', 'phone' => '0550000002', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    $siblingEnrollment = StudentAcademicEnrollment::create(['academic_year_id' => $f['year']->id, 'student_id' => $sibling->id, 'school_level_id' => $f['level']->id, 'school_group_id' => $f['group']->id, 'status' => StudentAcademicEnrollmentStatus::ENROLLED, 'enrollment_date' => '2026-09-01']);
    $personal = schoolStructure($f, ['student_id' => $f['student']->id, 'name' => 'Tarif Lina'], 30000);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.generate'), ['academic_year_id' => $f['year']->id, 'school_fee_structure_id' => $personal->id])->assertSessionHasNoErrors();
    expect($siblingEnrollment->financialAccount()->exists())->toBeFalse();

    app(TenantContext::class)->set($f['tenant']);
    $levelStructure = schoolStructure($f, ['school_level_id' => $f['level']->id, 'name' => 'Tarif 3AP'], 50000);
    $this->post(route('admin.school-payments.generate'), ['academic_year_id' => $f['year']->id, 'school_fee_structure_id' => $levelStructure->id, 'update_existing' => true])->assertSessionHasNoErrors();

    expect((float) $f['enrollment']->financialAccount()->value('expected_total'))->toBe(30000.0)
        ->and((float) $siblingEnrollment->financialAccount()->value('expected_total'))->toBe(50000.0);
});

test('a fee structure must schedule exactly its components total', function () {
    $f = schoolBillingFixture();
    $this->actingAs($f['admin'])->post(route('admin.school-payments.fee-structures.store'), [
        'academic_year_id' => $f['year']->id, 'name' => 'Incomplet',
        'components' => [['code' => 'tuition', 'label' => 'Scolarité', 'amount' => 100000]],
        'schedule' => [['label' => 'T1', 'amount' => 60000, 'due_date' => '2026-10-01', 'component_index' => 0]],
    ])->assertSessionHasErrors('schedule');
    expect(SchoolFeeStructure::count())->toBe(0);
});

test('enrollment lifecycle creates, closes and reopens the school account', function () {
    $f = schoolBillingFixture();
    $f['enrollment']->delete();
    $structure = schoolStructure($f, ['school_level_id' => $f['level']->id]);
    $structure->scheduleItems()->first()->update(['amount' => 20000]);
    $structure->scheduleItems()->create(['label' => 'T2', 'amount' => 30000, 'due_date' => today()->subDay(), 'sort_order' => 1]);
    $enrollment = StudentAcademicEnrollment::create(['academic_year_id' => $f['year']->id, 'student_id' => $f['student']->id, 'school_level_id' => $f['level']->id, 'school_group_id' => $f['group']->id, 'status' => StudentAcademicEnrollmentStatus::ENROLLED, 'enrollment_date' => '2026-09-01']);
    $account = $enrollment->financialAccount()->firstOrFail();
    expect((float) $account->balance)->toBe(50000.0)->and($account->school_fee_structure_id)->toBe($structure->id);

    $enrollment->update(['status' => StudentAcademicEnrollmentStatus::WITHDRAWN]);
    $account->refresh();
    expect($account->closed_at)->not->toBeNull()
        ->and((float) $account->balance)->toBe(30000.0)
        ->and($account->transactions()->where('type', 'waiver')->exists())->toBeTrue();

    $enrollment->update(['status' => StudentAcademicEnrollmentStatus::ENROLLED]);
    expect($account->fresh()->closed_at)->toBeNull()->and((float) $account->fresh()->balance)->toBe(50000.0);
});

test('the daily refresh marks installments past their due date as overdue', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 10000, today()->addDay()]]);
    $account->installments()->update(['due_date' => today()->subDay()]);
    app(TenantContext::class)->clear();

    $this->artisan('finance:refresh-statuses')->assertSuccessful();
    app(TenantContext::class)->set($f['tenant']);
    expect($account->fresh()->status)->toBe('overdue')->and($account->installments()->value('status'))->toBe('overdue');
});

test('manual receipt numbers only need to be unique within a school', function () {
    $first = schoolBillingFixture();
    $firstAccount = schoolLedgerAccount($first, [['T1', 10000, today()->addDay()]]);
    $this->actingAs($first['admin'])->post(route('admin.school-payments.payments.store', $firstAccount), ['amount' => 1000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash', 'reference' => 'REC-001'])->assertSessionHasNoErrors();
    $this->post(route('admin.school-payments.payments.store', $firstAccount), ['amount' => 1000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash', 'reference' => 'REC-001'])->assertSessionHasErrors('reference');

    $second = schoolBillingFixture();
    $secondAccount = schoolLedgerAccount($second, [['T1', 10000, today()->addDay()]]);
    $this->actingAs($second['admin'])->post(route('admin.school-payments.payments.store', $secondAccount), ['amount' => 1000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash', 'reference' => 'REC-001'])->assertSessionHasNoErrors();
});

test('school billing is only available to private schools and stays in its domain', function () {
    $f = schoolBillingFixture();
    $account = schoolLedgerAccount($f, [['T1', 10000, today()->addDay()]]);
    $account->update(['domain' => 'formation']);
    $this->actingAs($f['admin'])->post(route('admin.school-payments.payments.store', $account), ['amount' => 100, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertNotFound();

    $centre = Tenant::factory()->create(['organization_type' => 'training_center']);
    app(TenantContext::class)->set($centre);
    $centreAdmin = User::factory()->create(['tenant_id' => $centre->id, 'role' => UserRole::ADMIN]);
    $this->actingAs($centreAdmin)->get(route('admin.school-payments.index'))->assertNotFound();
});
