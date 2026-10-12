<?php

use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\FinancialAccount;
use App\Models\Formation;
use App\Models\FormationPricingConfig;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

function formationBillingFixture(string $suffix = 'one'): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'training_center']);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $student = Student::create(['first_name' => 'Ahmed', 'last_name' => 'Test', 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    $formation = Formation::create(['title' => "English {$suffix}", 'code' => "ENG-{$suffix}", 'duration_hours' => 40, 'price' => 28000, 'is_active' => true]);
    $session = EnrollmentForm::create(['course_id' => $formation->id, 'teacher_id' => $teacher->id, 'title' => "Oct 2026 - Jan 2027 {$suffix}", 'start_date' => '2026-10-01', 'end_date' => '2027-01-31', 'min_students' => 1, 'max_students' => 20, 'groups_count' => 1, 'students_per_group' => 20, 'is_active' => true]);
    $pricing = FormationPricingConfig::create(['course_id' => $formation->id, 'enrollment_form_id' => $session->id, 'name' => 'Registration + balance', 'total_price' => 28000, 'is_active' => true]);
    $pricing->scheduleItems()->createMany([
        ['label' => 'Registration', 'amount' => 8000, 'due_date' => '2026-10-01', 'sort_order' => 0],
        ['label' => 'Balance', 'amount' => 20000, 'due_date' => '2026-11-01', 'sort_order' => 1],
    ]);
    $enrollment = CourseEnrollment::create(['enrollment_form_id' => $session->id, 'student_id' => $student->id, 'status' => ApplicationStatus::REGISTERED, 'first_name' => 'Ahmed', 'last_name' => 'Test', 'email' => "ahmed-{$suffix}@example.test", 'phone' => '0550000000', 'confirmation_token' => str()->uuid(), 'registered_at' => now()]);

    return compact('tenant', 'admin', 'student', 'formation', 'session', 'pricing', 'enrollment');
}

test('registered enrollment gets idempotent billing from its formation pricing', function () {
    $f = formationBillingFixture();
    $account = $f['enrollment']->financialAccount()->firstOrFail();

    expect($account->domain)->toBe('formation')
        ->and($account->installments)->toHaveCount(2)
        ->and((float) $account->expected_total)->toBe(28000.0);

    $payload = ['formation_pricing_config_id' => $f['pricing']->id, 'session_id' => $f['session']->id];
    $this->actingAs($f['admin'])->post(route('admin.formation-payments.generate'), $payload)->assertSessionHasNoErrors();
    expect(FinancialAccount::count())->toBe(1)->and($account->installments()->count())->toBe(2);
});

test('multiple formation enrollments maintain independent balances and partial allocations', function () {
    $first = formationBillingFixture('b2');
    $secondFormation = Formation::create(['title' => 'Web Development', 'code' => 'WEB', 'duration_hours' => 80, 'price' => 45000, 'is_active' => true]);
    $secondSession = EnrollmentForm::create(['course_id' => $secondFormation->id, 'teacher_id' => User::factory()->create(['tenant_id' => $first['tenant']->id, 'role' => UserRole::TEACHER])->id, 'title' => 'Web autumn', 'start_date' => '2026-10-01', 'end_date' => '2027-02-01', 'min_students' => 1, 'max_students' => 20, 'groups_count' => 1, 'students_per_group' => 20, 'is_active' => true]);
    $secondPricing = FormationPricingConfig::create(['course_id' => $secondFormation->id, 'enrollment_form_id' => $secondSession->id, 'name' => 'Web price', 'total_price' => 45000, 'is_active' => true]);
    $secondPricing->scheduleItems()->create(['label' => 'Full price', 'amount' => 45000, 'due_date' => '2026-10-01', 'sort_order' => 0]);
    $secondEnrollment = CourseEnrollment::create(['enrollment_form_id' => $secondSession->id, 'student_id' => $first['student']->id, 'status' => ApplicationStatus::REGISTERED, 'first_name' => 'Ahmed', 'last_name' => 'Test', 'email' => 'ahmed-web@example.test', 'phone' => '0550000000', 'confirmation_token' => str()->uuid(), 'registered_at' => now()]);
    $firstAccount = $first['enrollment']->financialAccount()->firstOrFail();
    $secondAccount = $secondEnrollment->financialAccount()->firstOrFail();

    $this->actingAs($first['admin'])->post(route('admin.formation-payments.payments.store', $firstAccount), ['amount' => 10000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();

    expect((float) $firstAccount->fresh()->balance)->toBe(18000.0)
        ->and((float) $secondAccount->fresh()->balance)->toBe(45000.0)
        ->and($firstAccount->transactions()->firstOrFail()->allocations)->toHaveCount(2);
});

test('formation discounts and cancellations are auditable movements', function () {
    $f = formationBillingFixture('audit');
    $account = $f['enrollment']->financialAccount()->firstOrFail();
    $this->actingAs($f['admin'])->post(route('admin.formation-payments.payments.store', $account), ['amount' => 12000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'algeria_post'])->assertSessionHasNoErrors();
    $this->post(route('admin.formation-payments.movements.store', $account), ['type' => 'discount', 'amount' => 2000, 'reason' => 'Scholarship'])->assertSessionHasNoErrors();
    $this->post(route('admin.formation-payments.movements.store', $account), ['type' => 'refund', 'amount' => 1000, 'reason' => 'Not supported'])->assertSessionHasErrors('type');
    $discount = $account->transactions()->where('type', 'discount')->firstOrFail();
    $this->post(route('admin.formation-payments.transactions.cancel', $discount), ['reason' => 'Wrong student'])->assertSessionHasNoErrors();

    expect($account->transactions()->pluck('type')->all())->toContain('payment', 'discount', 'reversal')
        ->and((float) $account->fresh()->paid_total)->toBe(12000.0)
        ->and((float) $account->fresh()->balance)->toBe(16000.0);
});

test('formation financial accounts are tenant isolated', function () {
    $first = formationBillingFixture('tenant');
    $account = $first['enrollment']->financialAccount()->firstOrFail();
    $otherTenant = Tenant::factory()->create(['organization_type' => 'training_center']);
    app(TenantContext::class)->set($otherTenant);
    $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($otherAdmin)->get(route('admin.formation-payments.accounts.show', $account->id))->assertNotFound();
});

test('a cashier collects formation payments but cannot set prices or grant discounts', function () {
    $f = formationBillingFixture('cashier');
    app(\App\Services\DefaultTenantRoles::class)->provision($f['tenant']);
    $role = \App\Models\Role::create(['name' => 'Caisse', 'is_active' => true]);
    app(\App\Services\TenantRbac::class)->syncPermissions($role, ['payments.view', 'payments.collect']);
    $cashier = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::EMPLOYEE]);
    $cashier->roles()->attach($role->id, ['tenant_id' => $f['tenant']->id, 'data_scope' => 'tenant']);
    $account = $f['enrollment']->financialAccount()->firstOrFail();

    $this->actingAs($cashier)->post(route('admin.formation-payments.payments.store', $account), ['amount' => 1000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    $this->post(route('admin.formation-payments.movements.store', $account), ['type' => 'discount', 'amount' => 1000, 'reason' => 'Gift'])->assertForbidden();
    $this->post(route('admin.formation-payments.pricing.store'), ['course_id' => $f['formation']->id, 'name' => 'Cheap', 'total_price' => 1, 'schedule' => [['label' => 'All', 'amount' => 1, 'due_date' => '2026-10-01']]])->assertForbidden();
    $payment = $account->transactions()->where('type', 'payment')->firstOrFail();
    $this->post(route('admin.formation-payments.transactions.cancel', $payment), ['reason' => 'Oops'])->assertForbidden();
});

test('training plan enrollments are billed from group pricing or the level price', function () {
    $f = formationBillingFixture('plan');
    $level = \App\Models\CourseLevel::create(['course_id' => $f['formation']->id, 'name' => 'A1', 'code' => 'A1', 'price' => 18000, 'is_active' => true]);
    $teacher = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::TEACHER]);
    $plan = \App\Models\TrainingPlan::create(['course_level_id' => $level->id, 'teacher_id' => $teacher->id, 'title' => 'A1 soir', 'status' => 'scheduled']);
    $group = \App\Models\TrainingPlanGroup::create(['training_plan_id' => $plan->id, 'group_number' => 1, 'name' => 'A1-G1', 'capacity' => 10, 'is_active' => true]);
    $student = Student::create(['first_name' => 'Sara', 'last_name' => 'Plan', 'phone' => '0550000009', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);
    $enrollment = CourseEnrollment::create(['training_plan_group_id' => $group->id, 'student_id' => $student->id, 'status' => ApplicationStatus::REGISTERED, 'first_name' => 'Sara', 'last_name' => 'Plan', 'email' => 'sara@example.test', 'phone' => '0550000009', 'confirmation_token' => str()->uuid(), 'registered_at' => now()]);
    expect((float) $enrollment->financialAccount()->value('expected_total'))->toBe(18000.0);

    $this->actingAs($f['admin'])->post(route('admin.formation-payments.pricing.store'), ['course_id' => $f['formation']->id, 'training_plan_group_id' => $group->id, 'name' => 'Groupe A1', 'total_price' => 15000, 'schedule' => [['label' => 'Total', 'amount' => 15000, 'due_date' => '2026-10-01']]])->assertSessionHasNoErrors();
    $pricing = FormationPricingConfig::where('training_plan_group_id', $group->id)->firstOrFail();
    $this->post(route('admin.formation-payments.generate'), ['formation_pricing_config_id' => $pricing->id, 'group_id' => $group->id, 'update_existing' => true])->assertSessionHasNoErrors();
    expect((float) $enrollment->financialAccount()->value('expected_total'))->toBe(15000.0);
});

test('cancelling a formation enrollment closes its account', function () {
    $f = formationBillingFixture('cancel');
    $f['enrollment']->update(['status' => ApplicationStatus::CANCELLED, 'cancelled_at' => now()]);
    $account = $f['enrollment']->financialAccount()->firstOrFail();

    expect($account->closed_at)->not->toBeNull();
    $this->actingAs($f['admin'])->post(route('admin.formation-payments.payments.store', $account), ['amount' => 100, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasErrors('account');
});

test('parents see ledger payments and only download their children receipts', function () {
    $f = formationBillingFixture('parent');
    $account = $f['enrollment']->financialAccount()->firstOrFail();
    $this->actingAs($f['admin'])->post(route('admin.formation-payments.payments.store', $account), ['amount' => 8000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    app(TenantContext::class)->set($f['tenant']);
    $payment = $account->transactions()->where('type', 'payment')->firstOrFail();
    $parentUser = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::PARENT]);
    $parent = \App\Models\SchoolParent::create(['user_id' => $parentUser->id, 'first_name' => 'Nadia', 'last_name' => 'Test']);
    $parent->students()->attach($f['student']->id);
    $stranger = User::factory()->create(['tenant_id' => $f['tenant']->id, 'role' => UserRole::PARENT]);
    \App\Models\SchoolParent::create(['user_id' => $stranger->id, 'first_name' => 'Other', 'last_name' => 'Parent']);
    app(TenantContext::class)->clear();

    $this->actingAs($parentUser)->get('/parent/payments')->assertOk()->assertInertia(fn ($page) => $page->component('Parent/Payments')
        ->where('finance.totals.paid', fn ($paid) => (float) $paid === 8000.0)->where('finance.totals.balance', fn ($balance) => (float) $balance === 20000.0));
    $this->get("/parent/payments/{$payment->id}/receipt")->assertOk();
    $this->actingAs($stranger)->get("/parent/payments/{$payment->id}/receipt")->assertForbidden();
});
