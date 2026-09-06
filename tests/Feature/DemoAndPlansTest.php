<?php

use App\Enums\UserRole;
use App\Mail\DemoAccountApprovedMail;
use App\Mail\DemoAccountRejectedMail;
use App\Models\DemoRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('website captures a complete demo request capped at fifteen days', function () {
    $this->post(route('demo.store'), [
        'school_name' => 'École Horizon', 'school_type' => 'private_school',
        'contact_name' => 'Nadia Amrane', 'contact_role' => 'Directrice',
        'email' => 'nadia@horizon.test', 'phone' => '0550000000',
        'address' => '12 rue principale', 'wilaya' => 'Alger', 'commune' => 'Hydra',
        'students_count' => 320, 'teachers_count' => 24, 'staff_count' => 8,
        'sites_count' => 2, 'requested_days' => 16,
    ])->assertSessionHasErrors('requested_days');

    expect(DemoRequest::count())->toBe(0);
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

    $this->actingAs($superAdmin, 'super_admin')->post(route('super-admin.demo-requests.approve', $demo), ['days' => 12])->assertSessionHasNoErrors();

    $tenant = $demo->fresh()->tenant;
    expect($tenant->account_type)->toBe('demo')
        ->and(abs($tenant->demo_expires_at->diffInDays(now())))->toBeGreaterThanOrEqual(11)
        ->and(User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', UserRole::ADMIN)->exists())->toBeTrue();
    Mail::assertSent(DemoAccountApprovedMail::class);
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
        ->and($demo->rejection_reason)->toBe($reason);
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
