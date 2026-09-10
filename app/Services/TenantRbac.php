<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Enums\DataScope;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TenantRbac
{
    public function __construct(private readonly RbacAudit $audit) {}

    /** @param list<int> $roleIds */
    public function assignRoles(User $user, array $roleIds): void
    {
        $tenantId = $this->tenantId();
        $this->assertSameTenant($user->tenant_id);
        $roles = Role::whereIn('id', $roleIds)->where('is_active', true)->get();
        if ($roles->count() !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages(['role_ids' => 'Un rôle est invalide, inactif ou appartient à un autre établissement.']);
        }

        $old = $user->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
        $removesAdministrator = $user->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR)
            && ! $roles->contains('system_key', DefaultTenantRoles::TENANT_ADMINISTRATOR);
        if ($removesAdministrator) {
            $this->assertAnotherActiveAdministrator($user);
        }

        DB::transaction(function () use ($user, $roles, $tenantId, $old): void {
            $user->roles()->syncWithPivotValues($roles->pluck('id'), ['tenant_id' => $tenantId]);
            $this->audit->record('rbac.user_roles.changed', $user, ['role_ids' => $old], ['role_ids' => $roles->pluck('id')->all()]);
        });
        $user->unsetRelation('roles');
    }

    /** @param list<array{id:int,scope:string}> $assignments */
    public function assignRolesWithScopes(User $user, array $assignments): void
    {
        $tenantId = $this->tenantId();
        $this->assertSameTenant($user->tenant_id);
        $byRole = collect($assignments)->keyBy('id');
        $roles = Role::whereIn('id', $byRole->keys())->where('is_active', true)->get();
        if ($roles->count() !== $byRole->count()) {
            throw ValidationException::withMessages(['roles' => 'Un rôle est invalide, inactif ou appartient à un autre établissement.']);
        }
        $removesAdministrator = $user->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR)
            && ! $roles->contains('system_key', DefaultTenantRoles::TENANT_ADMINISTRATOR);
        if ($removesAdministrator) $this->assertAnotherActiveAdministrator($user);

        $payload = $roles->mapWithKeys(function (Role $role) use ($byRole, $tenantId) {
            $scope = DataScope::from($byRole[$role->id]['scope']);
            if ($role->system_key === DefaultTenantRoles::TENANT_ADMINISTRATOR) $scope = DataScope::TENANT;
            return [$role->id => ['tenant_id' => $tenantId, 'data_scope' => $scope->value]];
        })->all();
        $old = $user->roles()->get()->map(fn (Role $role) => ['id' => $role->id, 'scope' => $role->pivot->data_scope])->all();
        DB::transaction(function () use ($user, $payload, $old): void {
            $user->roles()->sync($payload);
            $this->audit->record('rbac.user_roles.changed', $user, ['roles' => $old], ['roles' => $payload]);
        });
        $user->unsetRelation('roles');
    }

    /** @param list<string> $permissionKeys */
    public function syncPermissions(Role $role, array $permissionKeys): void
    {
        $tenantId = $this->tenantId();
        $this->assertSameTenant($role->tenant_id);
        $keys = collect($permissionKeys)->map(fn (string $key) => PermissionCatalog::normalize($key))->unique()->values();
        $permissions = Permission::whereIn('key', $keys)->get();
        if ($permissions->count() !== $keys->count()) {
            throw ValidationException::withMessages(['permissions' => 'Une ou plusieurs permissions ne sont pas définies par la plateforme.']);
        }
        $old = $role->permissions()->pluck('permissions.key')->all();

        DB::transaction(function () use ($role, $permissions, $tenantId, $old): void {
            $role->permissions()->syncWithPivotValues($permissions->pluck('id'), ['tenant_id' => $tenantId]);
            $this->audit->record('rbac.role_permissions.changed', $role, ['permissions' => $old], ['permissions' => $permissions->pluck('key')->all()]);
        });
    }

    public function setActive(Role $role, bool $active): void
    {
        $this->assertSameTenant($role->tenant_id);
        if (! $active) {
            $this->assertCanDeactivateRole($role);
        }
        $old = $role->is_active;
        $role->update(['is_active' => $active]);
        $this->audit->record('rbac.role.status_changed', $role, ['is_active' => $old], ['is_active' => $active]);
    }

    public function deleteOrDeactivate(Role $role): void
    {
        $this->assertSameTenant($role->tenant_id);
        if ($role->system_key || $role->users()->exists()) {
            $this->setActive($role, false);

            return;
        }
        $snapshot = $role->only(['id', 'name', 'system_key']);
        $this->audit->record('rbac.role.deleted', $role, $snapshot, null);
        $role->delete();
    }

    public function assertCanDeactivateUser(User $user): void
    {
        if ($user->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR)) {
            $this->assertAnotherActiveAdministrator($user);
        }
    }

    public function assertCanDeactivateRole(Role $role): void
    {
        if ($role->system_key === DefaultTenantRoles::TENANT_ADMINISTRATOR) {
            $this->assertOtherAdministratorRoleActive($role);
        }
    }

    private function assertAnotherActiveAdministrator(User $except): void
    {
        $exists = User::where('is_active', true)->where('id', '!=', $except->id)
            ->whereHas('roles', fn ($query) => $query->where('roles.is_active', true)->where('roles.system_key', DefaultTenantRoles::TENANT_ADMINISTRATOR))
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['role_ids' => 'Le dernier administrateur actif de l’établissement ne peut pas être retiré ou désactivé.']);
        }
    }

    private function assertOtherAdministratorRoleActive(Role $role): void
    {
        $hasActiveAdministrator = User::where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereKey($role->id)->where('roles.is_active', true))->exists();
        if ($hasActiveAdministrator) {
            throw ValidationException::withMessages(['role' => 'Le rôle du dernier administrateur actif ne peut pas être désactivé.']);
        }
    }

    private function tenantId(): int
    {
        return app(TenantContext::class)->id() ?? throw new \LogicException('A tenant context is required for RBAC operations.');
    }

    private function assertSameTenant(?int $tenantId): void
    {
        if ($tenantId !== $this->tenantId()) {
            throw ValidationException::withMessages(['tenant' => 'Cette ressource appartient à un autre établissement.']);
        }
    }
}
