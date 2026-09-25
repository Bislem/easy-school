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

test('formation discounts and refunds are auditable movements', function () {
    $f = formationBillingFixture('audit');
    $account = $f['enrollment']->financialAccount()->firstOrFail();
    $this->actingAs($f['admin'])->post(route('admin.formation-payments.payments.store', $account), ['amount' => 12000, 'transaction_date' => today()->toDateString(), 'payment_method' => 'algeria_post'])->assertSessionHasNoErrors();
    $this->post(route('admin.formation-payments.movements.store', $account), ['type' => 'discount', 'amount' => 2000, 'reason' => 'Scholarship'])->assertSessionHasNoErrors();
    $this->post(route('admin.formation-payments.movements.store', $account), ['type' => 'refund', 'amount' => 1000, 'reason' => 'Approved refund'])->assertSessionHasNoErrors();

    expect($account->transactions()->pluck('type')->all())->toContain('payment', 'discount', 'refund')
        ->and((float) $account->fresh()->paid_total)->toBe(11000.0)
        ->and((float) $account->fresh()->balance)->toBe(15000.0);
});

test('formation financial accounts are tenant isolated', function () {
    $first = formationBillingFixture('tenant');
    $account = $first['enrollment']->financialAccount()->firstOrFail();
    $otherTenant = Tenant::factory()->create(['organization_type' => 'training_center']);
    app(TenantContext::class)->set($otherTenant);
    $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($otherAdmin)->get(route('admin.formation-payments.accounts.show', $account->id))->assertNotFound();
});
