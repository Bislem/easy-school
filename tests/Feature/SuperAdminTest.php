<?php

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;

function superAdminUser(array $attributes = []): User
{
    return User::factory()->create($attributes + [
        'role' => UserRole::SUPER_ADMIN, 'tenant_id' => null,
        'is_active' => true, 'can_login' => true,
    ]);
}

test('school administrators cannot access platform routes', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin, 'super_admin')->get(route('super-admin.dashboard'))->assertForbidden();
});

test('school users cannot authenticate through platform login', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'password' => 'password']);

    $this->post(route('super-admin.authenticate'), ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest('super_admin');
});

test('super administrators use an independent guard session', function () {
    $schoolAdmin = User::factory()->create(['role' => UserRole::ADMIN]);
    $superAdmin = superAdminUser(['password' => 'password']);
    $this->actingAs($schoolAdmin, 'web');

    $this->post(route('super-admin.authenticate'), ['email' => $superAdmin->email, 'password' => 'password'])
        ->assertRedirect(route('super-admin.dashboard'));
    $this->assertAuthenticatedAs($schoolAdmin, 'web');
    $this->assertAuthenticatedAs($superAdmin, 'super_admin');

    $this->post(route('super-admin.logout'))->assertRedirect(route('super-admin.login'));
    $this->assertAuthenticatedAs($schoolAdmin, 'web');
    $this->assertGuest('super_admin');
});

test('super administrator can create and suspend a school', function () {
    $plan = SubscriptionPlan::create(['name' => 'Essentiel', 'slug' => 'essentiel', 'price' => 5000, 'currency' => 'DZD', 'billing_period' => 'monthly', 'is_active' => true, 'sort_order' => 1]);
    $this->actingAs(superAdminUser(), 'super_admin')->post(route('super-admin.tenants.store'), [
        'name' => 'École Les Explorateurs', 'email' => 'contact@explorateurs.test',
        'admin_name' => 'Nadia Admin', 'admin_email' => 'nadia@explorateurs.test',
        'password' => 'Password123!',
        'subscription_plan_id' => $plan->id, 'payment_amount' => 5000,
    ])->assertSessionHasNoErrors();

    $school = Tenant::where('slug', 'ecole-les-explorateurs')->firstOrFail();
    $admin = User::withoutGlobalScopes()->where('email', 'nadia@explorateurs.test')->firstOrFail();
    expect($admin->tenant_id)->toBe($school->id)
        ->and($admin->role)->toBe(UserRole::ADMIN)
        ->and($school->subscription_plan_id)->toBe($plan->id)
        ->and(SubscriptionPayment::where('tenant_id', $school->id)->count())->toBe(1);

    $this->patch(route('super-admin.tenants.status', $school), ['status' => 'suspended'])->assertSessionHasNoErrors();
    expect($school->refresh()->status)->toBe('suspended');
});

test('archiving a school keeps its data', function () {
    $school = Tenant::factory()->create();

    $this->actingAs(superAdminUser(), 'super_admin')
        ->delete(route('super-admin.tenants.destroy', $school))
        ->assertRedirect(route('super-admin.tenants.index'));

    $this->assertSoftDeleted('tenants', ['id' => $school->id]);
});
