<?php

use App\Models\Tenant;
use App\Models\TenantStoredFile;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\TenantStorageService;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('tenant storage records metadata and updates counters on store and delete', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create(['storage_limit_bytes' => 10_000, 'storage_used_bytes' => 0]);
    app(TenantContext::class)->set($tenant);
    $service = app(TenantStorageService::class);
    $upload = UploadedFile::fake()->create('student-card.pdf', 2, 'application/pdf');

    $path = $service->store($upload, 'student-documents', 'public', TenantStorageService::STUDENT_DOCUMENTS, 'students');

    $metadata = TenantStoredFile::firstOrFail();
    expect($metadata->tenant_id)->toBe($tenant->id)
        ->and($metadata->storage_key)->toBe($path)
        ->and($metadata->original_filename)->toBe('student-card.pdf')
        ->and($metadata->size_bytes)->toBe($upload->getSize())
        ->and($tenant->fresh()->storage_used_bytes)->toBe($upload->getSize());

    $service->delete($path);
    Storage::disk('public')->assertMissing($path);
    expect(TenantStoredFile::count())->toBe(0)
        ->and($tenant->fresh()->storage_used_bytes)->toBe(0);
});

test('tenant storage rejects uploads over quota without writing a file', function () {
    Storage::fake('public');
    $tenant = Tenant::factory()->create(['storage_limit_bytes' => 100, 'storage_used_bytes' => 90]);
    app(TenantContext::class)->set($tenant);

    expect(fn () => app(TenantStorageService::class)->store(
        UploadedFile::fake()->create('too-large.pdf', 1, 'application/pdf'),
        'documents', 'public', TenantStorageService::OTHER,
    ))->toThrow(ValidationException::class);

    expect(TenantStoredFile::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and($tenant->fresh()->storage_used_bytes)->toBe(90);
});

test('a plan storage limit is synchronized to its tenant and enforced on upload', function () {
    Storage::fake('public');
    $plan = SubscriptionPlan::create([
        'name' => 'Starter Storage', 'slug' => 'starter-storage', 'price' => 1000,
        'currency' => 'DZD', 'billing_period' => 'monthly', 'storage_mb' => 1,
        'is_active' => true,
    ]);
    $tenant = Tenant::factory()->create(['storage_used_bytes' => 1024 * 1024 - 100]);
    $superAdmin = User::factory()->create([
        'role' => UserRole::SUPER_ADMIN, 'tenant_id' => null,
        'is_active' => true, 'can_login' => true,
    ]);

    $this->actingAs($superAdmin, 'super_admin')->put(route('super-admin.plans.assign'), [
        'tenant_id' => $tenant->id,
        'subscription_plan_id' => $plan->id,
        'plan_expires_at' => now()->addMonth()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($tenant->fresh()->storage_limit_bytes)->toBe(1024 * 1024);
    app(TenantContext::class)->set($tenant);

    expect(fn () => app(TenantStorageService::class)->store(
        UploadedFile::fake()->create('over-plan-limit.pdf', 1, 'application/pdf'),
        'documents', 'public', TenantStorageService::OTHER,
    ))->toThrow(ValidationException::class);

    expect(TenantStoredFile::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('storage repair is isolated and recalculates counters from metadata', function () {
    $first = Tenant::factory()->create(['storage_used_bytes' => 999]);
    $second = Tenant::factory()->create(['storage_used_bytes' => 777]);
    TenantStoredFile::withoutGlobalScopes()->create(['tenant_id' => $first->id, 'disk' => 'public', 'storage_key' => 'one', 'original_filename' => 'one.pdf', 'size_bytes' => 123, 'category' => 'other']);
    TenantStoredFile::withoutGlobalScopes()->create(['tenant_id' => $second->id, 'disk' => 'public', 'storage_key' => 'two', 'original_filename' => 'two.pdf', 'size_bytes' => 456, 'category' => 'other']);

    app(TenantStorageService::class)->repair($first);

    expect($first->fresh()->storage_used_bytes)->toBe(123)
        ->and($second->fresh()->storage_used_bytes)->toBe(777);
});

test('school administrator sees backend driven account subscription and storage data', function () {
    $plan = SubscriptionPlan::create(['name' => 'Pro', 'slug' => 'storage-pro', 'price' => 1000, 'currency' => 'DZD', 'billing_period' => 'monthly', 'storage_mb' => 10, 'max_students' => 200, 'features' => ['Présences'], 'is_active' => true]);
    $tenant = Tenant::factory()->create(['status' => 'active', 'account_type' => 'paid', 'subscription_plan_id' => $plan->id, 'plan_started_at' => now(), 'plan_expires_at' => now()->addDays(20), 'storage_used_bytes' => 2 * 1024 * 1024, 'storage_limit_bytes' => 10 * 1024 * 1024]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN, 'is_active' => true, 'can_login' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)->get(route('admin.account.index'))->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Admin/Account/Index')
            ->where('account.status', 'active')
            ->where('storage.percentage', 20)
            ->where('plan.limits.students', 200)
            ->where('plan.limits.storage_bytes', 10 * 1024 * 1024));
});
