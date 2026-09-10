<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DataScope;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantRbac;
use App\Services\DefaultTenantRoles;
use App\Services\RbacAudit;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RolesController extends Controller
{
    public function __construct(private readonly TenantRbac $rbac, private readonly RbacAudit $audit) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::with('permissions:id,key')->withCount('users')->orderBy('name')->get(),
            'permissions' => PermissionCatalog::all(),
            'data_scopes' => array_column(DataScope::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedRole($request);
        $role = Role::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => true]);
        $this->rbac->syncPermissions($role, $data['permissions']);
        $this->audit->record('rbac.role.created', $role, null, $role->only(['name', 'description', 'is_active']));

        return back()->with('success', 'Rôle créé.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validatedRole($request, $role);
        $old = $role->only(['name', 'description']);
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $this->rbac->syncPermissions($role, $data['permissions']);
        $this->audit->record('rbac.role.updated', $role, $old, $role->only(['name', 'description']));

        return back()->with('success', 'Rôle mis à jour.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->rbac->deleteOrDeactivate($role);

        return back()->with('success', 'Rôle supprimé ou désactivé.');
    }

    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150', Rule::unique('roles')->where('tenant_id', app(\App\Tenancy\TenantContext::class)->id())]]);
        $copy = Role::create(['name' => $data['name'], 'description' => $role->description, 'is_active' => true]);
        $this->rbac->syncPermissions($copy, $role->permissions()->pluck('key')->all());
        return back()->with('success', 'Rôle dupliqué.');
    }

    public function toggle(Role $role): RedirectResponse
    {
        $this->rbac->setActive($role, ! $role->is_active);
        return back()->with('success', $role->is_active ? 'Rôle activé.' : 'Rôle désactivé.');
    }

    public function restore(Role $role): RedirectResponse
    {
        abort_unless($role->system_key && isset(DefaultTenantRoles::templates()[$role->system_key]), 422, 'Ce rôle ne possède pas de modèle par défaut.');
        $template = DefaultTenantRoles::templates()[$role->system_key];
        $role->update(['name' => $template['name'], 'description' => null, 'is_active' => true]);
        $this->rbac->syncPermissions($role, $template['permissions']);
        return back()->with('success', 'Rôle restauré à sa configuration par défaut.');
    }

    public function assign(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*.id' => ['required', 'integer'],
            'roles.*.scope' => ['required', Rule::enum(DataScope::class)],
            'site_ids' => ['sometimes', 'array'],
            'site_ids.*' => ['integer', Rule::exists('school_sites', 'id')],
        ]);
        $oldSites = $user->schoolSites()->pluck('school_sites.id')->all();
        $this->rbac->assignRolesWithScopes($user, $data['roles']);
        $user->schoolSites()->syncWithPivotValues($data['site_ids'] ?? [], ['tenant_id' => app(\App\Tenancy\TenantContext::class)->id()]);
        $this->audit->record('rbac.user_sites.changed', $user, ['site_ids' => $oldSites], ['site_ids' => $data['site_ids'] ?? []]);

        return back()->with('success', 'Rôles et périmètres mis à jour.');
    }

    private function validatedRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('roles')->where('tenant_id', app(\App\Tenancy\TenantContext::class)->id())->ignore($role)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', Rule::in(array_keys(PermissionCatalog::all()))],
        ]);
    }
}
