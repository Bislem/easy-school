<?php

use App\Enums\UserRole;
use App\Mail\DemoAccountApprovedMail;
use App\Mail\DemoAccountRejectedMail;
use App\Mail\SchoolCredentialsRegeneratedMail;
use App\Models\DemoRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('website accepts a demo request longer than fifteen days', function () {
    $this->post(route('demo.store'), [
        'school_name' => 'École Horizon', 'school_type' => 'private_school',
        'contact_name' => 'Nadia Amrane', 'contact_role' => 'Directrice',
        'email' => 'nadia@horizon.test', 'phone' => '0550000000',
        'address' => '12 rue principale', 'wilaya' => 'Alger', 'commune' => 'Hydra',
        'students_count' => 320, 'teachers_count' => 24, 'staff_count' => 8,
        'sites_count' => 2, 'requested_days' => 16,
    ])->assertSessionHasNoErrors();

    expect(DemoRequest::query()->value('requested_days'))->toBe(16);
});

test('super admin approves a demo with an editable expiry and isolated account', function () {
    Mail::fake();
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true, 'can_login' => true]);
    $demo = DemoRequest::create([
        'school_name' => 'École Horizon', 'school_type' => 'private_school',
        'contact_name' => 'Nadia Amrane', 'contact_role' => 'Directrice',
        'email' => 'demo@horizon.test', 'phone' => '0550000000', 'address' => 'Rue 1',
        'wilaya' => 'Alger', 'commune' => 'Hydra', 'students_count' => 100,
        'teachers_count' => 10, 'staff_count' => 3, 'sites_count' => 1,
        'requested_days' => 7, 'status' => 'pending',
    ]);

    $this->actingAs($superAdmin, 'super_admin')->post(route('super-admin.demo-requests.approve', $demo), ['days' => 45])->assertSessionHasNoErrors();

    $tenant = $demo->fresh()->tenant;
    expect($tenant->account_type)->toBe('demo')
        ->and(abs($tenant->demo_expires_at->diffInDays(now())))->toBeGreaterThanOrEqual(44)
        ->and(User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', UserRole::ADMIN)->exists())->toBeTrue();
    $approvedMail = null;
    Mail::assertSent(DemoAccountApprovedMail::class, function (DemoAccountApprovedMail $mail) use (&$approvedMail) {
        $approvedMail = $mail;

        return $mail->hasTo('demo@horizon.test');
    });

    expect($approvedMail)->not->toBeNull()
        ->and($approvedMail->temporaryPassword)->toMatch('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{14}$/')
        ->and($approvedMail->render())->toContain($approvedMail->temporaryPassword);

    Auth::shouldUse('web');
    $response = $this->post(route('login.store'), [
        'email' => 'demo@horizon.test',
        'password' => $approvedMail->temporaryPassword,
    ]);

    $this->assertAuthenticated('web');
    expect(auth('web')->user()->email)->toBe('demo@horizon.test')
        ->and(auth('web')->user()->tenant_id)->toBe($tenant->id);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('super admin must provide a rejection reason and the responsible receives it', function () {
    Mail::fake();
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true, 'can_login' => true]);
    $demo = DemoRequest::create([
        'school_name' => 'École Atlas', 'school_type' => 'private_school',
        'contact_name' => 'Sarah Amari', 'contact_role' => 'Directrice',
        'email' => 'sarah@atlas.test', 'phone' => '0550000001', 'address' => 'Rue 2',
        'wilaya' => 'Alger', 'commune' => 'Kouba', 'students_count' => 80,
        'teachers_count' => 8, 'staff_count' => 2, 'sites_count' => 1,
        'requested_days' => 15, 'status' => 'pending',
    ]);

    $this->actingAs($superAdmin, 'super_admin')
        ->post(route('super-admin.demo-requests.reject', $demo), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $reason = 'Le justificatif fourni ne permet pas de valider la demande.';
    $this->post(route('super-admin.demo-requests.reject', $demo), ['reason' => $reason])
        ->assertSessionHasNoErrors();

    expect($demo->fresh()->status)->toBe('rejected')
        ->and($demo->fresh()->rejection_reason)->toBe($reason);
    Mail::assertSent(DemoAccountRejectedMail::class, fn ($mail) =>
        $mail->hasTo('sarah@atlas.test') && $mail->demoRequest->rejection_reason === $reason
    );
});

test('demo accounts cannot update passwords or school information', function () {
    $tenant = Tenant::factory()->create(['account_type' => 'demo', 'demo_expires_at' => now()->addDays(7)]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN, 'password' => 'password']);

    $this->actingAs($admin)->put(route('password.update'), ['current_password' => 'password', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertForbidden();
    $this->actingAs($admin)->put(route('admin.settings.update'), [])->assertForbidden();
});

test('super admin regenerates client credentials and chooses the delivery email', function () {
    Mail::fake();
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['account_type' => 'demo', 'status' => 'active']);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::ADMIN,
        'email' => 'client@school.test',
        'password' => 'OldPassword123!',
        'is_active' => false,
        'can_login' => false,
    ]);

    $this->actingAs($superAdmin, 'super_admin')->post(
        route('super-admin.tenants.credentials', $tenant),
        ['delivery_email' => 'owner@example.test'],
    )->assertSessionHasNoErrors();

    $sentMail = null;
    Mail::assertSent(SchoolCredentialsRegeneratedMail::class, function ($mail) use (&$sentMail) {
        $sentMail = $mail;

        return $mail->hasTo('owner@example.test');
    });
    $admin->refresh();
    expect($sentMail)->not->toBeNull()
        ->and($sentMail->temporaryPassword)->toMatch('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{14}$/')
        ->and($sentMail->render())->toContain($sentMail->temporaryPassword)
        ->and(Hash::check($sentMail->temporaryPassword, $admin->password))->toBeTrue()
        ->and(Hash::check('OldPassword123!', $admin->password))->toBeFalse()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->can_login)->toBeTrue()
        ->and($admin->email)->toBe('client@school.test');
});

test('regenerated credentials default to the client administrator email', function () {
    Mail::fake();
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null]);
    $tenant = Tenant::factory()->create(['account_type' => 'paid']);
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => UserRole::ADMIN,
        'email' => 'admin@client.test',
    ]);

    $this->actingAs($superAdmin, 'super_admin')->post(
        route('super-admin.tenants.credentials', $tenant),
        ['delivery_email' => ''],
    )->assertSessionHasNoErrors();

    Mail::assertSent(
        SchoolCredentialsRegeneratedMail::class,
        fn ($mail) => $mail->hasTo('admin@client.test'),
    );
});

test('super admin converts a demo into a paid plan without losing client data', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true, 'can_login' => true]);
    $tenant = Tenant::factory()->create([
        'name' => 'École Démo', 'address' => '12 rue des Écoles', 'account_type' => 'demo',
        'status' => 'active', 'demo_expires_at' => now()->addDays(5),
    ]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $plan = SubscriptionPlan::create([
        'name' => 'Premium', 'slug' => 'premium-conversion', 'price' => 25000,
        'currency' => 'DZD', 'billing_period' => 'monthly', 'is_active' => true,
    ]);

    $this->actingAs($superAdmin, 'super_admin')->post(route('super-admin.tenants.subscription', $tenant), [
        'action' => 'change', 'subscription_plan_id' => $plan->id, 'months' => 6,
    ])->assertSessionHasNoErrors();

    $tenant->refresh();
    expect($tenant->account_type)->toBe('paid')
        ->and($tenant->subscription_plan_id)->toBe($plan->id)
        ->and($tenant->demo_expires_at)->toBeNull()
        ->and($tenant->address)->toBe('12 rue des Écoles')
        ->and($tenant->plan_expires_at->isAfter(now()->addMonths(5)))->toBeTrue()
        ->and(User::withoutGlobalScopes()->find($admin->id))->not->toBeNull();

    $this->actingAs($admin)->put(route('admin.settings.update'), [])->assertSessionHasErrors();
});

test('super admin manages plans and assigns one to a school', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true, 'can_login' => true]);
    $school = Tenant::factory()->create();
    $this->actingAs($superAdmin, 'super_admin')->post(route('super-admin.plans.store'), [
        'name' => 'Pro', 'price' => 12000, 'currency' => 'DZD', 'billing_period' => 'monthly',
        'max_students' => 500, 'max_teachers' => 40, 'max_staff' => 20, 'max_sites' => 3,
        'max_users' => 600, 'max_courses' => 100, 'storage_go' => 10,
        'features' => "Finance\nPrésences\nRapports", 'is_active' => true, 'sort_order' => 1,
    ])->assertSessionHasNoErrors();
    $plan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();

    $this->put(route('super-admin.plans.assign'), ['tenant_id' => $school->id, 'subscription_plan_id' => $plan->id, 'plan_expires_at' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
    expect($school->refresh()->subscription_plan_id)->toBe($plan->id)
        ->and($plan->features)->toBe(['Finance', 'Présences', 'Rapports'])
        ->and($plan->storage_mb)->toBe(10240);
});
