<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolCycle;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

function academicAdmin(string $type = 'private_school'): array
{
    $tenant = Tenant::factory()->create(['organization_type' => $type]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    return compact('tenant', 'admin');
}

function yearPayload(string $name, string $start, string $end): array
{
    return ['name' => $name, 'start_date' => $start, 'end_date' => $end, 'notes' => null];
}

test('private school creates an academic year with three editable trimesters', function () {
    ['tenant' => $tenant, 'admin' => $admin] = academicAdmin();
    $this->actingAs($admin)->post('/admin/academic-years', yearPayload('2026-2027', '2026-09-01', '2027-06-30'))->assertSessionHasNoErrors();
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::where('name', '2026-2027')->firstOrFail();
    expect($year->status->value)->toBe('draft')->and($year->periods()->count())->toBe(3)->and($year->periods()->pluck('number')->all())->toBe([1, 2, 3]);
    app(TenantContext::class)->clear();
});

test('private school creates default trimesters when the create form sends an empty periods array', function () {
    ['tenant' => $tenant, 'admin' => $admin] = academicAdmin();

    $payload = [...yearPayload('2027-2028', '2027-09-01', '2028-06-30'), 'periods' => []];
    $this->actingAs($admin)->post('/admin/academic-years', $payload)->assertSessionHasNoErrors();

    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::where('name', '2027-2028')->firstOrFail();
    expect($year->periods()->count())->toBe(3);
    app(TenantContext::class)->clear();
});

test('private school manages paid school breaks inside an academic year', function () {
    ['tenant' => $tenant, 'admin' => $admin] = academicAdmin();
    $this->actingAs($admin)->post('/admin/academic-years', yearPayload('2027-2028', '2027-09-01', '2028-06-30'));
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::where('name', '2027-2028')->firstOrFail();
    app(TenantContext::class)->clear();

    $this->post("/admin/academic-years/{$year->id}/calendar-events", [
        'name' => "Vacances d'hiver",
        'type' => 'winter_break',
        'starts_on' => '2027-12-19',
        'ends_on' => '2028-01-02',
        'applies_to' => 'both',
        'is_paid_for_teachers' => true,
        'notes' => null,
    ])->assertSessionHasNoErrors();

    app(TenantContext::class)->set($tenant);
    expect($year->calendarEvents()->firstOrFail()->is_paid_for_teachers)->toBeTrue();
    app(TenantContext::class)->clear();
});

test('only one year can be active while another future year remains draft', function () {
    ['tenant' => $tenant, 'admin' => $admin] = academicAdmin();
    $this->actingAs($admin)->post('/admin/academic-years', yearPayload('2026-2027', '2026-09-01', '2027-06-30'));
    $this->post('/admin/academic-years', yearPayload('2027-2028', '2027-09-01', '2028-06-30'));
    app(TenantContext::class)->set($tenant); $years = AcademicYear::orderBy('name')->get(); app(TenantContext::class)->clear();
    $this->patch("/admin/academic-years/{$years[0]->id}/activate")->assertSessionHasNoErrors();
    $this->patch("/admin/academic-years/{$years[1]->id}/activate")->assertSessionHasErrors('academic_year');
    expect($years[1]->fresh()->status->value)->toBe('draft');
});

test('student academic history is independent across years', function () {
    ['tenant' => $tenant] = academicAdmin(); app(TenantContext::class)->set($tenant);
    $cycle = SchoolCycle::create(['name' => 'CEM', 'code' => 'CEM', 'sort_order' => 1]);
    $two = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '2AM', 'code' => '2AM', 'sort_order' => 1]);
    $three = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '3AM', 'code' => '3AM', 'sort_order' => 2]);
    $first = AcademicYear::create([...yearPayload('2026-2027', '2026-09-01', '2027-06-30'), 'status' => 'active']);
    $second = AcademicYear::create([...yearPayload('2027-2028', '2027-09-01', '2028-06-30'), 'status' => 'draft']);
    $student = Student::create(['first_name' => 'Ahmed', 'last_name' => 'Test', 'phone' => '0550000000', 'is_active' => true]);
    StudentAcademicEnrollment::create(['academic_year_id' => $first->id, 'student_id' => $student->id, 'school_level_id' => $two->id, 'status' => 'completed', 'enrollment_date' => '2026-09-01']);
    $first->update(['status' => 'closed']);
    $next = StudentAcademicEnrollment::create(['academic_year_id' => $second->id, 'student_id' => $student->id, 'school_level_id' => $three->id, 'status' => 'enrolled', 'enrollment_date' => '2027-09-01']);
    $next->update(['status' => 'transferred']);
    expect($student->academicEnrollments()->where('academic_year_id', $first->id)->first()->school_level_id)->toBe($two->id)
        ->and($student->academicEnrollments()->count())->toBe(2);
    app(TenantContext::class)->clear();
});

test('active year can be closed without deleting history', function () {
    ['tenant' => $tenant, 'admin' => $admin] = academicAdmin(); app(TenantContext::class)->set($tenant);
    $year = AcademicYear::create([...yearPayload('2026-2027', '2026-09-01', '2027-06-30'), 'status' => 'active']); app(TenantContext::class)->clear();
    $this->actingAs($admin)->patch("/admin/academic-years/{$year->id}/close")->assertSessionHasNoErrors();
    expect($year->fresh()->status->value)->toBe('closed');
});

test('academic year access is private-school-only and tenant isolated', function () {
    ['admin' => $centerAdmin] = academicAdmin('training_center');
    $this->actingAs($centerAdmin)->get('/admin/academic-years')->assertNotFound();
    ['tenant' => $firstTenant, 'admin' => $firstAdmin] = academicAdmin(); ['tenant' => $otherTenant] = academicAdmin();
    app(TenantContext::class)->set($otherTenant); $foreign = AcademicYear::create([...yearPayload('2028-2029', '2028-09-01', '2029-06-30'), 'status' => 'draft']); app(TenantContext::class)->clear();
    $this->actingAs($firstAdmin)->put("/admin/academic-years/{$foreign->id}", yearPayload('Changed', '2028-09-01', '2029-06-30'))->assertNotFound();
});
