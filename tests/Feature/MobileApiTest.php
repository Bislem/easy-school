<?php

use App\Enums\UserRole;
use App\Mail\ParentTemporaryPasswordMail;
use App\Models\MobileMembership;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\StudentObservation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

function mobileTenantUser(Tenant $tenant, UserRole $role, string $email): User
{
    app(TenantContext::class)->set($tenant);
    try {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role, 'email' => $email, 'password' => 'password']);
    } finally {
        app(TenantContext::class)->clear();
    }
}

function mobileParentProfile(Tenant $tenant, User $user): SchoolParent
{
    app(TenantContext::class)->set($tenant);
    try {
        $parent = SchoolParent::create(['user_id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Amari']);
        MobileMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'parent', 'parent_id' => $parent->id]);

        return $parent;
    } finally {
        app(TenantContext::class)->clear();
    }
}

test('mobile login accepts parent accounts and rejects student-only accounts', function () {
    $tenant = Tenant::factory()->create(['slug' => 'atlas-school', 'status' => 'active']);
    $parentUser = mobileTenantUser($tenant, UserRole::PARENT, 'parent@atlas.test');
    mobileParentProfile($tenant, $parentUser);
    $studentUser = mobileTenantUser($tenant, UserRole::STUDENT, 'learner@atlas.test');

    $this->postJson('/api/mobile/v1/login', ['email' => 'parent@atlas.test', 'password' => 'password', 'device_name' => 'Test phone'])
        ->assertOk()->assertJsonPath('data.user.role', 'parent')->assertJsonCount(1, 'data.contexts');
    $this->postJson('/api/mobile/v1/login', ['email' => $studentUser->email, 'password' => 'password', 'device_name' => 'Test phone'])
        ->assertForbidden()->assertJsonPath('error', 'role_not_allowed');
});

test('one parent login lists all schools and creates a school-isolated token', function () {
    $schoolA = Tenant::factory()->create(['name' => 'School A', 'status' => 'active']);
    $schoolB = Tenant::factory()->create(['name' => 'School B', 'status' => 'active']);
    $user = mobileTenantUser($schoolA, UserRole::PARENT, 'one-parent@example.test');
    mobileParentProfile($schoolA, $user);
    mobileParentProfile($schoolB, $user);

    $login = $this->postJson('/api/mobile/v1/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Test phone'])
        ->assertOk()->assertJsonCount(2, 'data.contexts')->assertJsonPath('data.context_required', true);
    $bootstrapToken = $login->json('data.token');

    $this->withToken($bootstrapToken)->getJson('/api/mobile/v1/parent/children')->assertStatus(409);
    $this->withToken($bootstrapToken)->postJson('/api/mobile/v1/context', ['tenant_id' => $schoolB->id, 'role' => 'parent', 'device_name' => 'Test phone'])
        ->assertOk()->assertJsonPath('data.context.school.id', $schoolB->id)->assertJsonPath('data.context.role', 'parent');
    $this->withToken($bootstrapToken)->postJson('/api/mobile/v1/context', ['tenant_id' => $schoolB->id, 'role' => 'student', 'device_name' => 'Test phone'])
        ->assertUnprocessable();
});

test('a parent sees linked children but cannot inspect another student', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $parentUser = mobileTenantUser($tenant, UserRole::PARENT, 'parent@school.test');
    $parent = mobileParentProfile($tenant, $parentUser);
    app(TenantContext::class)->set($tenant);
    $linked = Student::create(['first_name' => 'Yanis', 'last_name' => 'Amari', 'email' => 'yanis@school.test', 'phone' => '0550000000']);
    $unlinked = Student::create(['first_name' => 'Sara', 'last_name' => 'Brahimi', 'email' => 'sara@school.test', 'phone' => '0550000001']);
    $parent->students()->attach($linked, ['is_primary' => true]);
    app(TenantContext::class)->clear();
    $membership = MobileMembership::where('parent_id', $parent->id)->firstOrFail();
    $token = $parentUser->createToken('test-parent', ['mobile', 'membership:'.$membership->id])->plainTextToken;

    $this->withToken($token)->getJson('/api/mobile/v1/parent/children')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $linked->id);
    $this->withToken($token)->getJson("/api/mobile/v1/parent/children/{$unlinked->id}")->assertForbidden();
});

test('student mobile routes no longer exist', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $parentUser = mobileTenantUser($tenant, UserRole::PARENT, 'parent2@school.test');
    mobileParentProfile($tenant, $parentUser);
    Sanctum::actingAs($parentUser, ['mobile']);

    $this->getJson('/api/mobile/v1/student/profile')->assertNotFound();
    $this->getJson('/api/mobile/v1/student/planning')->assertNotFound();
});

test('a parent can register and login before any school links the account', function () {
    $registration = $this->postJson('/api/mobile/v1/register', [
        'first_name' => 'Nadia',
        'last_name' => 'Amari',
        'email' => 'NADIA@EXAMPLE.TEST',
        'phone' => '0550000000',
        'password' => 'StrongPassword123!',
        'password_confirmation' => 'StrongPassword123!',
        'device_name' => 'Nadia phone',
    ])->assertCreated()
        ->assertJsonCount(0, 'data.contexts')
        ->assertJsonPath('data.school', null)
        ->assertJsonPath('data.user.email', 'nadia@example.test');

    expect(User::withoutGlobalScopes()->where('email', 'nadia@example.test')->value('tenant_id'))->toBeNull();
    $this->withToken($registration->json('data.token'))->getJson('/api/mobile/v1/contexts')->assertOk()->assertJsonCount(0, 'data');
});

test('password reset is non-enumerating and requires replacing the temporary password', function () {
    Mail::fake();
    $user = User::factory()->create(['tenant_id' => null, 'role' => UserRole::PARENT, 'email' => 'reset-parent@example.test']);

    $this->postJson('/api/mobile/v1/reset-password', ['email' => $user->email])->assertOk();
    $this->postJson('/api/mobile/v1/reset-password', ['email' => 'missing@example.test'])->assertOk();

    $user->refresh();
    expect($user->temporary_password_expires_at)->not->toBeNull();
    Mail::assertSent(ParentTemporaryPasswordMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && Hash::check($mail->temporaryPassword, $user->password);
    });

    $login = $this->postJson('/api/mobile/v1/login', ['email' => $user->email, 'password' => Mail::sent(ParentTemporaryPasswordMail::class)->first()->temporaryPassword, 'device_name' => 'Phone'])
        ->assertOk()->assertJsonPath('data.password_change_required', true);
    $this->withToken($login->json('data.token'))->putJson('/api/mobile/v1/password', [
        'password' => 'ReplacementPassword123!',
        'password_confirmation' => 'ReplacementPassword123!',
    ])->assertOk();
    expect($user->refresh()->temporary_password_expires_at)->toBeNull();
});

test('hidden children disappear from the parent API and direct access is denied', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $parentUser = mobileTenantUser($tenant, UserRole::PARENT, 'visibility-parent@example.test');
    $parent = mobileParentProfile($tenant, $parentUser);
    app(TenantContext::class)->set($tenant);
    $child = Student::create(['first_name' => 'Meriem', 'last_name' => 'Amari', 'phone' => '0550000003']);
    $parent->students()->attach($child, ['is_visible' => false, 'tenant_id' => $tenant->id]);
    app(TenantContext::class)->clear();
    $membership = MobileMembership::where('parent_id', $parent->id)->firstOrFail();
    $token = $parentUser->createToken('test-parent', ['mobile', 'membership:'.$membership->id])->plainTextToken;

    $this->withToken($token)->getJson('/api/mobile/v1/parent/children')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($token)->getJson("/api/mobile/v1/parent/children/{$child->id}")->assertForbidden();
});

test('a school reuses an existing global parent without changing account data', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $parentUser = User::factory()->create([
        'tenant_id' => null,
        'role' => UserRole::PARENT,
        'name' => 'Original Parent',
        'first_name' => 'Original',
        'last_name' => 'Parent',
        'email' => 'existing-parent@example.test',
        'phone' => '0550000044',
    ]);
    app(TenantContext::class)->set($tenant);
    $student = Student::create(['first_name' => 'Child', 'last_name' => 'One', 'phone' => '0550000045']);
    app(TenantContext::class)->clear();

    $this->actingAs($admin)->post(route('admin.parents.store'), [
        'email' => 'existing-parent@example.test',
        'first_name' => 'Changed',
        'last_name' => 'Name',
        'phone' => '0000',
        'relationship' => 'Mère',
        'student_ids' => [$student->id],
    ])->assertSessionHasNoErrors();

    expect($parentUser->refresh()->name)->toBe('Original Parent');
    $profile = SchoolParent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('user_id', $parentUser->id)->firstOrFail();
    expect($profile->first_name)->toBe('Original')
        ->and((bool) $profile->students()->first()->pivot->is_visible)->toBeTrue()
        ->and(MobileMembership::where('parent_id', $profile->id)->exists())->toBeTrue();
    Mail::assertNothingSent();
});

test('a school-created parent receives a temporary password email', function () {
    Mail::fake();
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    app(TenantContext::class)->set($tenant);
    $student = Student::create(['first_name' => 'Child', 'last_name' => 'Two', 'phone' => '0550000055']);
    app(TenantContext::class)->clear();

    $this->actingAs($admin)->post(route('admin.parents.store'), [
        'email' => 'new-parent@example.test',
        'first_name' => 'New',
        'last_name' => 'Parent',
        'phone' => '0550000056',
        'student_ids' => [$student->id],
    ])->assertSessionHasNoErrors();

    $user = User::withoutGlobalScopes()->where('email', 'new-parent@example.test')->firstOrFail();
    expect($user->tenant_id)->toBeNull()->and($user->temporary_password_expires_at)->not->toBeNull();
    Mail::assertSent(ParentTemporaryPasswordMail::class, fn ($mail) => $mail->hasTo($user->email) && $mail->reason === 'account_created');
});

test('a parent can read and update global profile information', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
        'role' => UserRole::PARENT,
        'name' => 'Old Name',
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'profile-parent@example.test',
        'phone' => '0550000060',
    ]);
    $token = $user->createToken('profile-test', ['mobile', 'context:select'])->plainTextToken;

    $this->withToken($token)->getJson('/api/mobile/v1/profile')
        ->assertOk()->assertJsonPath('data.first_name', 'Old');
    $this->withToken($token)->patchJson('/api/mobile/v1/profile', [
        'first_name' => 'New',
        'last_name' => 'Identity',
        'email' => 'profile-parent@example.test',
        'phone' => '0550000061',
    ])->assertOk()
        ->assertJsonPath('data.name', 'New Identity')
        ->assertJsonPath('meta.email_changed', false);

    expect($user->refresh()->phone)->toBe('0550000061');
});

test('changing parent email requires the current password and changing password revokes other devices', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
        'role' => UserRole::PARENT,
        'email' => 'secure-parent@example.test',
        'password' => 'CurrentPassword123!',
    ]);
    $currentToken = $user->createToken('current-device', ['mobile', 'context:select'])->plainTextToken;
    $otherToken = $user->createToken('other-device', ['mobile', 'context:select'])->plainTextToken;

    $profile = [
        'first_name' => 'Secure',
        'last_name' => 'Parent',
        'email' => 'changed-parent@example.test',
        'phone' => null,
    ];
    $this->withToken($currentToken)->patchJson('/api/mobile/v1/profile', $profile)
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->withToken($currentToken)->patchJson('/api/mobile/v1/profile', [...$profile, 'current_password' => 'CurrentPassword123!'])
        ->assertOk()->assertJsonPath('meta.email_changed', true);
    expect($user->tokens()->count())->toBe(1);

    $newOtherToken = $user->createToken('new-other-device', ['mobile', 'context:select'])->plainTextToken;
    $this->withToken($currentToken)->putJson('/api/mobile/v1/password', [
        'current_password' => 'CurrentPassword123!',
        'password' => 'ChangedPassword123!',
        'password_confirmation' => 'ChangedPassword123!',
    ])->assertOk();
    expect($user->tokens()->count())->toBe(1);
    expect(Hash::check('ChangedPassword123!', $user->refresh()->password))->toBeTrue();
});

test('a parent can log out every mobile device', function () {
    $user = User::factory()->create(['tenant_id' => null, 'role' => UserRole::PARENT]);
    $token = $user->createToken('device', ['mobile', 'context:select'])->plainTextToken;

    $this->withToken($token)->postJson('/api/mobile/v1/logout-all')->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('a parent can reply to an observation for a visible child', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $parentUser = mobileTenantUser($tenant, UserRole::PARENT, 'reply-parent@example.test');
    $parent = mobileParentProfile($tenant, $parentUser);
    $teacher = mobileTenantUser($tenant, UserRole::TEACHER, 'reply-teacher@example.test');
    app(TenantContext::class)->set($tenant);
    $child = Student::create(['first_name' => 'Reply', 'last_name' => 'Child', 'phone' => '0550000070']);
    $parent->students()->attach($child, ['is_visible' => true, 'tenant_id' => $tenant->id]);
    $observation = StudentObservation::create(['student_id' => $child->id, 'author_id' => $teacher->id, 'message' => 'Please review this.']);
    app(TenantContext::class)->clear();
    $membership = MobileMembership::where('parent_id', $parent->id)->firstOrFail();
    $token = $parentUser->createToken('reply-device', ['mobile', 'membership:'.$membership->id])->plainTextToken;

    $this->withToken($token)->postJson("/api/mobile/v1/parent/children/{$child->id}/observations/{$observation->id}/replies", [
        'message' => 'Thank you, I have reviewed it.',
    ])->assertCreated()
        ->assertJsonPath('data.author.role', 'parent')
        ->assertJsonPath('data.message', 'Thank you, I have reviewed it.');

    expect(StudentObservation::withoutGlobalScopes()->where('parent_id', $observation->id)->count())->toBe(1);
});
