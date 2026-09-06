<?php

use App\Enums\UserRole;
use App\Models\BadgeTemplate;
use App\Models\CompanySetting;
use App\Models\EmployeeType;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a school can register its isolated workspace', function () {
    Storage::fake('local');
    $plan = SubscriptionPlan::create(['name' => 'Essentiel', 'slug' => 'essentiel', 'price' => 5000, 'currency' => 'DZD', 'billing_period' => 'monthly', 'is_active' => true, 'sort_order' => 1]);
    $response = $this->post(route('tenant.register.store'), [
        'name' => 'École El Feth',
        'admin_name' => 'Nadia Amrane',
        'email' => 'nadia@elfeth.test',
        'phone' => '0550000000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'subscription_plan_id' => $plan->id,
        'payment_proof' => UploadedFile::fake()->create('recu.pdf', 100, 'application/pdf'),
    ]);

    $tenant = Tenant::where('email', 'nadia@elfeth.test')->firstOrFail();
    $admin = User::withoutGlobalScopes()->where('email', 'nadia@elfeth.test')->firstOrFail();

    $response->assertRedirect(route('account.pending'));
    $this->assertAuthenticatedAs($admin);
    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->role)->toBe(UserRole::ADMIN)
        ->and($tenant->status)->toBe('pending')
        ->and($tenant->subscription_plan_id)->toBe($plan->id)
        ->and(Storage::disk('local')->exists($tenant->payment_proof_path))->toBeTrue()
        ->and(CompanySetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('trading_name'))->toBe('École El Feth')
        ->and(EmployeeType::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(8)
        ->and(BadgeTemplate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_default', true)->exists())->toBeTrue();
});

test('registration ignores a submitted tenant id', function () {
    Storage::fake('local');
    $other = Tenant::factory()->create();
    $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 10000, 'currency' => 'DZD', 'billing_period' => 'monthly', 'is_active' => true, 'sort_order' => 1]);

    $this->post(route('tenant.register.store'), [
        'name' => 'École Nouvelle', 'admin_name' => 'Admin', 'email' => 'admin@new.test',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'tenant_id' => $other->id,
        'subscription_plan_id' => $plan->id,
        'payment_proof' => UploadedFile::fake()->image('recu.jpg'),
    ])->assertRedirect(route('account.pending'));

    $tenant = Tenant::where('email', 'admin@new.test')->firstOrFail();
    expect(User::withoutGlobalScopes()->where('email', 'admin@new.test')->value('tenant_id'))->toBe($tenant->id)
        ->and($tenant->id)->not->toBe($other->id);
});
