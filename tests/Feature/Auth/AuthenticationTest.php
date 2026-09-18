<?php

use App\Enums\UserRole;
use App\Models\MobileMembership;
use App\Models\SchoolParent;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('parent login screen can be rendered', function () {
    $this->get(route('parent.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Parent/Auth/Login'));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('inertia logins perform a full navigation with the regenerated session', function () {
    $user = User::factory()->create();

    $response = $this->withHeader('X-Inertia', 'true')->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));
});

test('parents must use their dedicated login portal', function () {
    $parent = User::factory()->create([
        'role' => UserRole::PARENT,
        'is_active' => true,
        'can_login' => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $parent->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('parents can authenticate using the parent login portal', function () {
    $parent = User::factory()->create([
        'role' => UserRole::PARENT,
        'is_active' => true,
        'can_login' => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->post(route('parent.login.store'), [
        'email' => $parent->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($parent);
    $response->assertRedirect(route('parent.dashboard', absolute: false));
});

test('global parent accounts authenticate through their active school membership', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $parent = User::factory()->create([
        'tenant_id' => null,
        'role' => UserRole::PARENT,
        'is_active' => true,
        'can_login' => true,
        'email_verified_at' => now(),
    ]);
    app(TenantContext::class)->set($tenant);
    $profile = SchoolParent::create([
        'user_id' => $parent->id,
        'first_name' => 'Nadia',
        'last_name' => 'Kaci',
    ]);
    app(TenantContext::class)->clear();
    MobileMembership::create([
        'user_id' => $parent->id,
        'tenant_id' => $tenant->id,
        'role' => UserRole::PARENT,
        'parent_id' => $profile->id,
        'is_active' => true,
    ]);

    $this->post(route('parent.login.store'), [
        'email' => $parent->email,
        'password' => 'password',
    ])->assertRedirect(route('parent.dashboard', absolute: false))
        ->assertSessionHas('parent.tenant_id', $tenant->id);

    $this->assertAuthenticatedAs($parent);
    $this->get(route('parent.dashboard'))->assertOk();
});

test('non parent users cannot authenticate through the parent login portal', function () {
    $user = User::factory()->create();

    $response = $this->post(route('parent.login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('parents return to the parent login portal after logout', function () {
    $parent = User::factory()->create(['role' => UserRole::PARENT]);

    $response = $this->actingAs($parent)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('parent.login'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(implode('|', [$user->email, '127.0.0.1']), amount: 10);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $errors = session('errors');

    $this->assertStringContainsString('Too many login attempts', $errors->first('email'));
});
