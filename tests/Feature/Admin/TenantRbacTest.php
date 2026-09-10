<?php

use App\Enums\StaffPermission;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultTenantRoles;
use App\Services\TenantRbac;
use App\Services\AuthorizationService;
use App\Enums\DataScope;
use App\Models\SchoolSite;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

function rbacTenant(string $suffix): array
{
    $tenant = Tenant::factory()->create(['name' => "RBAC {$suffix}", 'organization_type' => 'private_school']);
    app(DefaultTenantRoles::class)->provision($tenant);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin', 'is_active' => true]);
    app(DefaultTenantRoles::class)->provision($tenant);

    return compact('tenant', 'admin');
}

test('default role templates are tenant scoped and administrator receives every permission', function () {
    ['tenant' => $tenant, 'admin' => $admin] = rbacTenant('defaults');

    expect(Role::count())->toBe(count(DefaultTenantRoles::templates()))
        ->and(Role::whereNotNull('system_key')->count())->toBe(count(DefaultTenantRoles::templates()))
        ->and($admin->fresh()->roles)->toHaveCount(1)
        ->and($admin->fresh()->hasPermission('employees.delete'))->toBeTrue()
        ->and($admin->fresh()->hasPermission('roles.manage'))->toBeTrue()
        ->and(Permission::count())->toBe(count(\App\Support\PermissionCatalog::all()));
});

test('users can receive multiple roles and permissions are combined', function () {
    ['tenant' => $tenant] = rbacTenant('multiple');
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $roles = Role::whereIn('system_key', ['hr', 'finance'])->get();

    app(TenantRbac::class)->assignRoles($user, $roles->pluck('id')->all());

    expect($user->fresh()->roles)->toHaveCount(2)
        ->and($user->fresh()->hasPermission('employees.view'))->toBeTrue()
        ->and($user->fresh()->hasPermission('payments.collect'))->toBeTrue()
        ->and($user->fresh()->hasPermission('students.delete'))->toBeFalse();
});

test('new legacy administrators and teachers receive their compatible system role', function () {
    ['tenant' => $tenant] = rbacTenant('legacy-compatibility');
    $administrator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'teacher']);

    expect($administrator->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR))->toBeTrue()
        ->and($teacher->hasSystemRole('teacher'))->toBeTrue()
        ->and($teacher->hasPermission('timetables.view'))->toBeTrue()
        ->and($teacher->hasPermission('roles.manage'))->toBeFalse();
});

test('existing gate keys resolve through customizable tenant permissions', function () {
    ['tenant' => $tenant, 'admin' => $admin] = rbacTenant('gates');
    $hrUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $hrRole = Role::where('system_key', 'hr')->firstOrFail();
    app(TenantRbac::class)->assignRoles($hrUser, [$hrRole->id]);

    expect(Gate::forUser($hrUser)->allows(StaffPermission::VIEW_ANY->value))->toBeTrue()
        ->and(Gate::forUser($hrUser)->allows(StaffPermission::CREATE->value))->toBeTrue();

    $otherAdmin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    app(TenantRbac::class)->assignRoles($admin, []);
    expect(Gate::forUser($admin->fresh())->denies(StaffPermission::VIEW_ANY->value))->toBeTrue()
        ->and($otherAdmin->hasPermission('roles.manage'))->toBeTrue();
});

test('role assignment cannot cross tenant boundaries at service or database level', function () {
    $first = rbacTenant('first');
    $firstRole = Role::where('system_key', 'finance')->firstOrFail();
    $second = rbacTenant('second');
    $secondUser = $second['admin'];

    expect(fn () => app(TenantRbac::class)->assignRoles($secondUser, [$firstRole->id]))->toThrow(ValidationException::class);

    expect(fn () => DB::table('role_user')->insert([
        'tenant_id' => $second['tenant']->id, 'role_id' => $firstRole->id, 'user_id' => $secondUser->id,
    ]))->toThrow(QueryException::class);
});

test('the final active tenant administrator cannot be removed or deactivated', function () {
    ['admin' => $admin] = rbacTenant('last-admin');
    $administratorRole = Role::where('system_key', DefaultTenantRoles::TENANT_ADMINISTRATOR)->firstOrFail();

    expect(fn () => app(TenantRbac::class)->assignRoles($admin, []))->toThrow(ValidationException::class)
        ->and(fn () => $admin->update(['is_active' => false]))->toThrow(ValidationException::class)
        ->and(fn () => $administratorRole->update(['is_active' => false]))->toThrow(ValidationException::class);
});

test('an administrator can be removed when another active administrator remains', function () {
    ['tenant' => $tenant, 'admin' => $first] = rbacTenant('two-admins');
    $second = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin', 'is_active' => true]);
    app(DefaultTenantRoles::class)->provision($tenant);

    app(TenantRbac::class)->assignRoles($first, []);
    $first->update(['is_active' => false]);

    expect($first->fresh()->is_active)->toBeFalse()->and($second->fresh()->hasPermission('roles.manage'))->toBeTrue();
});

test('tenant customization survives idempotent template provisioning', function () {
    ['tenant' => $tenant] = rbacTenant('custom');
    $finance = Role::where('system_key', 'finance')->firstOrFail();
    $finance->update(['name' => 'Comptabilité personnalisée']);
    app(TenantRbac::class)->syncPermissions($finance, ['payments.view']);

    app(DefaultTenantRoles::class)->provision($tenant);
    $finance->refresh();

    expect($finance->name)->toBe('Comptabilité personnalisée')
        ->and($finance->permissions()->pluck('key')->all())->toBe(['payments.view']);
});

test('role assignments and permission changes are audited', function () {
    ['tenant' => $tenant, 'admin' => $admin] = rbacTenant('audit');
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $finance = Role::where('system_key', 'finance')->firstOrFail();
    $this->actingAs($admin);

    app(TenantRbac::class)->assignRoles($user, [$finance->id]);
    app(TenantRbac::class)->syncPermissions($finance, ['payments.view', 'payments.collect']);

    expect(AuditLog::where('event', 'rbac.user_roles.changed')->where('related_id', $user->id)->count())->toBe(1)
        ->and(AuditLog::where('event', 'rbac.role_permissions.changed')->count())->toBe(1);
});

test('referenced roles are deactivated and system identity cannot be changed', function () {
    ['tenant' => $tenant] = rbacTenant('identity');
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $finance = Role::where('system_key', 'finance')->firstOrFail();
    app(TenantRbac::class)->assignRoles($user, [$finance->id]);

    app(TenantRbac::class)->deleteOrDeactivate($finance);

    expect($finance->fresh()->is_active)->toBeFalse()
        ->and(fn () => $finance->update(['system_key' => 'changed']))->toThrow(ValidationException::class);
});

test('permission keys and system role identities remain platform controlled', function () {
    rbacTenant('platform-identities');
    $permission = Permission::where('key', 'students.view')->firstOrFail();

    expect(fn () => $permission->update(['key' => 'students.read']))->toThrow(ValidationException::class)
        ->and(fn () => $permission->delete())->toThrow(ValidationException::class)
        ->and(fn () => Permission::create(['key' => 'custom.unmanaged', 'name' => 'Unmanaged']))->toThrow(ValidationException::class)
        ->and(fn () => Role::create(['name' => 'Custom', 'system_key' => 'custom_system_role']))->toThrow(ValidationException::class);
});

test('sensitive routes use distinct permissions and unknown admin routes fail closed', function () {
    $authorization = app(AuthorizationService::class);

    expect($authorization->permissionForRoute('admin.salaries.generate', 'POST'))->toBe('salaries.approve')
        ->and($authorization->permissionForRoute('admin.finance.payments.reverse', 'POST'))->toBe('payments.cancel')
        ->and($authorization->permissionForRoute('admin.finance.payments.refund', 'POST'))->toBe('payments.refund')
        ->and($authorization->permissionForRoute('admin.reports.export', 'GET'))->toBe('reports.export')
        ->and($authorization->permissionForRoute('admin.users.roles.assign', 'PUT'))->toBe('roles.manage')
        ->and($authorization->permissionForRoute('admin.unmapped.secret', 'GET'))->toBeNull();
});

test('combined roles select the broadest scope for each permission', function () {
    ['tenant' => $tenant] = rbacTenant('scopes');
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $first = Role::create(['name' => 'Scoped students one', 'is_active' => true]);
    $second = Role::create(['name' => 'Scoped students two', 'is_active' => true]);
    app(TenantRbac::class)->syncPermissions($first, ['students.view']);
    app(TenantRbac::class)->syncPermissions($second, ['students.view']);
    app(TenantRbac::class)->assignRolesWithScopes($user, [
        ['id' => $first->id, 'scope' => DataScope::OWN->value],
        ['id' => $second->id, 'scope' => DataScope::ASSIGNED->value],
    ]);

    expect(app(AuthorizationService::class)->scope($user->fresh(), 'students.view'))->toBe(DataScope::ASSIGNED);
});

test('tenant administrator scope cannot be narrowed', function () {
    ['admin' => $admin] = rbacTenant('administrator-scope');
    $role = Role::where('system_key', DefaultTenantRoles::TENANT_ADMINISTRATOR)->firstOrFail();
    app(TenantRbac::class)->assignRolesWithScopes($admin, [['id' => $role->id, 'scope' => DataScope::OWN->value]]);

    expect(app(AuthorizationService::class)->scope($admin->fresh(), 'students.view'))->toBe(DataScope::TENANT)
        ->and($admin->fresh()->roles()->firstOrFail()->pivot->data_scope)->toBe(DataScope::TENANT->value);
});

test('access management pages require access permissions', function () {
    ['tenant' => $tenant, 'admin' => $admin] = rbacTenant('access-pages');
    $financeUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    app(TenantRbac::class)->assignRoles($financeUser, [Role::where('system_key', 'finance')->firstOrFail()->id]);

    $this->actingAs($financeUser)->get(route('admin.access.roles'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.access.roles'))->assertOk();
    $this->actingAs($admin)->get(route('admin.access.permissions'))->assertOk();
    $this->actingAs($admin)->get(route('admin.access.history'))->assertOk();
});

test('custom roles can be duplicated and tenant defaults can be restored', function () {
    ['admin' => $admin] = rbacTenant('role-workflows');
    $finance = Role::where('system_key', 'finance')->firstOrFail();
    app(TenantRbac::class)->syncPermissions($finance, ['payments.view']);

    $this->actingAs($admin)->post(route('admin.users.roles.duplicate', $finance), ['name' => 'Finance copy'])->assertSessionHasNoErrors();
    expect(Role::where('name', 'Finance copy')->firstOrFail()->permissions()->pluck('key')->all())->toBe(['payments.view']);

    $this->actingAs($admin)->post(route('admin.users.roles.restore', $finance))->assertSessionHasNoErrors();
    expect($finance->fresh()->permissions()->count())->toBe(count(DefaultTenantRoles::templates()['finance']['permissions']));
});

test('role assignment stores scopes and selected sites', function () {
    ['tenant' => $tenant, 'admin' => $admin] = rbacTenant('site-assignment');
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'employee']);
    $role = Role::where('system_key', 'general_supervision')->firstOrFail();
    $site = SchoolSite::create(['name' => 'Annexe', 'code' => 'ANNEXE', 'wilaya' => 'Alger', 'is_active' => true]);

    $this->actingAs($admin)->put(route('admin.users.roles.assign', $user), [
        'roles' => [['id' => $role->id, 'scope' => 'site']], 'site_ids' => [$site->id],
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->roles()->firstOrFail()->pivot->data_scope)->toBe('site')
        ->and($user->fresh()->schoolSites()->pluck('school_sites.id')->all())->toBe([$site->id]);
});
