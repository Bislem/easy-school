<?php

use App\Enums\UserRole;
use App\Models\BadgeTemplate;
use App\Models\CompanySetting;
use App\Models\EmployeeType;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;

test('a school can register its isolated workspace', function () {
    $this->travelTo(now()->startOfSecond());
    $response = $this->post(route('tenant.register.store'), [
        'name' => 'École El Feth',
        'admin_name' => 'Nadia Amrane',
        'email' => 'nadia@elfeth.test',
        'phone' => '0550000000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_type' => 'private_school',
    ]);

    $tenant = Tenant::where('email', 'nadia@elfeth.test')->firstOrFail();
    $admin = User::withoutGlobalScopes()->where('email', 'nadia@elfeth.test')->firstOrFail();

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);
    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->role)->toBe(UserRole::ADMIN)
        ->and($tenant->status)->toBe('active')
        ->and($tenant->account_type)->toBe('demo')
        ->and($tenant->subscription_plan_id)->toBeNull()
        ->and($tenant->trial_started_at->equalTo(now()))->toBeTrue()
        ->and($tenant->demo_expires_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and(CompanySetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('trading_name'))->toBe('École El Feth')
        ->and(EmployeeType::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(8)
        ->and(BadgeTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_default', true)->exists())->toBeTrue();
});

test('registration ignores a submitted tenant id', function () {
    $other = Tenant::factory()->create();

    $this->post(route('tenant.register.store'), [
        'name' => 'École Nouvelle', 'admin_name' => 'Admin', 'email' => 'admin@new.test',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'tenant_id' => $other->id,
        'organization_type' => 'training_center',
    ])->assertRedirect(route('dashboard'));

    $tenant = Tenant::where('email', 'admin@new.test')->firstOrFail();
    expect(User::withoutGlobalScopes()->where('email', 'admin@new.test')->value('tenant_id'))->toBe($tenant->id)
        ->and($tenant->id)->not->toBe($other->id);
});

test('an expired trial is blocked without deleting data and a plan restores access', function () {
    $tenant = Tenant::factory()->create([
        'account_type' => 'demo',
        'trial_started_at' => now()->subDays(31),
        'demo_expires_at' => now()->subDay(),
    ]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);

    $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('trial.expired'));
    $this->get(route('trial.expired'))->assertOk()->assertInertia(fn ($page) => $page->component('auth/TrialExpired'));
    $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    $this->assertDatabaseHas('users', ['id' => $admin->id]);

    $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'trial-pro', 'price' => 10000, 'currency' => 'DZD', 'billing_period' => 'monthly', 'is_active' => true]);
    $tenant->update(['account_type' => 'paid', 'subscription_plan_id' => $plan->id, 'plan_started_at' => now(), 'plan_expires_at' => now()->addMonth()]);

    $this->get(route('dashboard'))->assertOk();
});
