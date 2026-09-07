<?php

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\PrivateSchoolCampaignLevel;
use App\Models\PrivateSchoolInscription;
use App\Models\PrivateSchoolInscriptionCampaign;
use App\Models\SchoolCycle;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

function privateSchoolFixture(): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $cycle = SchoolCycle::create(['name' => 'Primaire', 'code' => 'PRI', 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '1AP', 'code' => '1AP', 'sort_order' => 1, 'is_active' => true]);
    $campaign = PrivateSchoolInscriptionCampaign::create(['academic_year_id' => $year->id, 'title' => 'Inscription 2026/2027', 'status' => 'open']);
    $campaignLevel = PrivateSchoolCampaignLevel::create(['campaign_id' => $campaign->id, 'school_level_id' => $level->id, 'max_places' => 1, 'is_open' => true]);
    app(TenantContext::class)->clear();

    return compact('tenant', 'admin', 'year', 'level', 'campaign', 'campaignLevel');
}

function publicSchoolPayload(int $campaignLevelId, string $studentEmail = 'child@example.com'): array
{
    return ['parent_first_name' => 'Nadia', 'parent_last_name' => 'Amrane', 'parent_email' => 'nadia@example.com', 'parent_phone' => '0550000011', 'relationship' => 'Mère', 'student_first_name' => 'Lina', 'student_last_name' => 'Amrane', 'student_email' => $studentEmail, 'student_phone' => '0550000022', 'birth_date' => '2019-01-01', 'campaign_level_id' => $campaignLevelId];
}

test('admin creates a tenant scoped campaign with required academic year and levels', function () {
    ['tenant' => $tenant, 'admin' => $admin, 'year' => $year, 'level' => $level] = privateSchoolFixture();
    $this->actingAs($admin)->post('/admin/inscription-campaigns', ['academic_year_id' => $year->id, 'title' => 'Deuxième campagne', 'status' => 'draft', 'levels' => [['school_level_id' => $level->id, 'max_places' => null, 'is_open' => true]]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('private_school_inscription_campaigns', ['tenant_id' => $tenant->id, 'academic_year_id' => $year->id, 'title' => 'Deuxième campagne']);
});

test('school inscription web entry point opens the admin module', function () {
    ['admin' => $admin] = privateSchoolFixture();

    $this->actingAs($admin)->get('/school-inscription')
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/PrivateSchoolInscriptions/Index'));
});

test('public inscription creates linked parent and student and prevents a duplicate request', function () {
    ['campaign' => $campaign, 'campaignLevel' => $campaignLevel] = privateSchoolFixture();
    $url = route('public.private-school-inscription.store', $campaign->public_token);
    $this->post($url, publicSchoolPayload($campaignLevel->id))->assertSessionHasNoErrors()->assertSessionHas('private_school_inscription_submitted');
    expect(PrivateSchoolInscription::withoutGlobalScope('tenant')->count())->toBe(1);
    $this->post($url, publicSchoolPayload($campaignLevel->id))->assertSessionHasErrors('campaign_level_id');
    expect(Student::withoutGlobalScope('tenant')->where('email', 'child@example.com')->count())->toBe(1)
        ->and(PrivateSchoolInscription::withoutGlobalScope('tenant')->count())->toBe(1);
});

test('an existing student can submit in a later academic year', function () {
    ['tenant' => $tenant, 'campaignLevel' => $campaignLevel] = privateSchoolFixture();
    $this->post(route('public.private-school-inscription.store', $campaignLevel->campaign->public_token), publicSchoolPayload($campaignLevel->id))->assertSessionHasNoErrors();
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::create(['name' => '2027-2028', 'start_date' => '2027-09-01', 'end_date' => '2028-06-30', 'status' => 'draft']);
    $campaign = PrivateSchoolInscriptionCampaign::create(['academic_year_id' => $year->id, 'title' => 'Inscription 2027/2028', 'status' => 'open']);
    $nextLevel = PrivateSchoolCampaignLevel::create(['campaign_id' => $campaign->id, 'school_level_id' => $campaignLevel->school_level_id, 'is_open' => true]);
    app(TenantContext::class)->clear();
    $this->post(route('public.private-school-inscription.store', $campaign->public_token), publicSchoolPayload($nextLevel->id))->assertSessionHasNoErrors();
    expect(Student::withoutGlobalScope('tenant')->where('email', 'child@example.com')->count())->toBe(1)
        ->and(PrivateSchoolInscription::withoutGlobalScope('tenant')->count())->toBe(2);
});

test('capacity counts accepted inscriptions and blocks over acceptance', function () {
    ['admin' => $admin, 'campaign' => $campaign, 'campaignLevel' => $campaignLevel] = privateSchoolFixture();
    foreach ([['Lina', 'one@example.com'], ['Sami', 'two@example.com']] as [$name, $email]) {
        $payload = publicSchoolPayload($campaignLevel->id, $email);
        $payload['student_first_name'] = $name;
        $payload['student_phone'] = fake()->unique()->numerify('055#######');
        $payload['parent_email'] = $name.'@parent.test';
        $payload['parent_phone'] = fake()->unique()->numerify('066#######');
        $this->post(route('public.private-school-inscription.store', $campaign->public_token), $payload)->assertSessionHasNoErrors();
    }
    $items = PrivateSchoolInscription::withoutGlobalScope('tenant')->orderBy('id')->get();
    $this->actingAs($admin)->patch("/admin/school-inscriptions/{$items[0]->id}/status", ['status' => 'accepted'])->assertSessionHasNoErrors();
    $this->patch("/admin/school-inscriptions/{$items[1]->id}/status", ['status' => 'accepted'])->assertSessionHasErrors('status');
    expect($items[1]->fresh()->status->value)->toBe('pending');
});
