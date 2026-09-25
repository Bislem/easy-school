<?php

use App\Enums\ApplicationStatus;
use App\Enums\StudentAcademicEnrollmentStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\FinancialAccount;
use App\Models\Formation;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FinancialAccountService;
use App\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

function paymentsOverviewFixture(): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $student = Student::create(['first_name' => 'Nadia', 'last_name' => 'Test', 'phone' => '0550000000', 'status' => StudentStatus::ACTIVE, 'is_active' => true]);

    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $cycle = SchoolCycle::create(['name' => 'Primaire', 'code' => 'PRI', 'sort_order' => 1, 'is_active' => true]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => 'Primary 3', 'code' => 'P3', 'sort_order' => 1, 'is_active' => true]);
    $group = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'name' => 'Group A', 'code' => 'A', 'capacity' => 20, 'is_active' => true]);
    $schoolEnrollment = StudentAcademicEnrollment::create(['academic_year_id' => $year->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'school_group_id' => $group->id, 'status' => StudentAcademicEnrollmentStatus::ENROLLED, 'enrollment_date' => '2026-09-01']);
    $schoolAccount = FinancialAccount::create(['accountable_type' => $schoolEnrollment->getMorphClass(), 'accountable_id' => $schoolEnrollment->id, 'domain' => 'school', 'student_id' => $student->id, 'academic_year_id' => $year->id]);
    $schoolAccount->installments()->create(['source_key' => 'school-one', 'label' => 'Tuition', 'amount' => 100000, 'due_date' => '2026-10-01', 'sort_order' => 0]);

    $formation = Formation::create(['title' => 'English B2', 'code' => 'EN-B2', 'duration_hours' => 40, 'price' => 30000, 'is_active' => true]);
    $session = EnrollmentForm::create(['course_id' => $formation->id, 'teacher_id' => $teacher->id, 'title' => 'Oct-Jan', 'start_date' => '2026-10-01', 'end_date' => '2027-01-31', 'min_students' => 1, 'max_students' => 20, 'groups_count' => 1, 'students_per_group' => 20, 'is_active' => true]);
    $formationEnrollment = CourseEnrollment::create(['enrollment_form_id' => $session->id, 'student_id' => $student->id, 'status' => ApplicationStatus::REGISTERED, 'first_name' => 'Nadia', 'last_name' => 'Test', 'email' => 'nadia-overview@example.test', 'phone' => '0550000000', 'confirmation_token' => str()->uuid(), 'registered_at' => now()]);
    $formationAccount = FinancialAccount::create(['accountable_type' => $formationEnrollment->getMorphClass(), 'accountable_id' => $formationEnrollment->id, 'domain' => 'formation', 'student_id' => $student->id]);
    $formationAccount->installments()->create(['source_key' => 'formation-one', 'label' => 'Formation price', 'amount' => 30000, 'due_date' => '2026-10-01', 'sort_order' => 0]);

    $ledger = app(FinancialAccountService::class);
    $ledger->refresh($schoolAccount);
    $ledger->refresh($formationAccount);
    $ledger->recordPayment($schoolAccount, ['amount' => 40000, 'transaction_date' => '2026-10-05', 'payment_method' => 'cash'], $admin->id);
    $ledger->recordPayment($formationAccount, ['amount' => 10000, 'transaction_date' => '2026-10-06', 'payment_method' => 'bank_transfer'], $admin->id);

    return compact('tenant', 'admin', 'schoolAccount', 'formationAccount');
}

test('payments overview aggregates school and formation ledgers without mixing them', function () {
    $fixture = paymentsOverviewFixture();

    $this->actingAs($fixture['admin'])->get(route('admin.payments-overview.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PaymentsOverview/Index')
            ->where('totals.school.expected', 100000)
            ->where('totals.school.collected', 40000)
            ->where('totals.formation.expected', 30000)
            ->where('totals.formation.collected', 10000)
            ->where('totals.combined.outstanding', 80000)
            ->has('recentTransactions', 2));
});

test('payments overview source filters and drill down links are read only', function () {
    $fixture = paymentsOverviewFixture();

    $this->actingAs($fixture['admin'])->get(route('admin.payments-overview.index', ['source' => 'formation']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.school.expected', 0)
            ->where('totals.formation.expected', 30000)
            ->where('recentTransactions.0.url', route('admin.formation-payments.accounts.show', $fixture['formationAccount'])));

    expect(collect(app('router')->getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.payments-overview'))->every(fn ($route) => in_array('GET', $route->methods(), true)))->toBeTrue();
});

test('payments overview remains tenant isolated', function () {
    $fixture = paymentsOverviewFixture();
    $otherTenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($otherTenant);
    $otherAdmin = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($otherAdmin)->get(route('admin.payments-overview.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.combined.expected', 0)
            ->has('recentTransactions', 0)
            ->has('outstanding.data', 0));
});
