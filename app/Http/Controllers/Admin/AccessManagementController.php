<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SchoolSite;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccessManagementController extends Controller
{
    public function users(Request $request, AuthorizationService $authorization): Response
    {
        $users = User::query()->whereNotNull('tenant_id')->with(['roles.permissions:id,key', 'schoolSites:id,name'])
            ->when($request->string('search')->trim()->toString(), fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(15)->withQueryString();
        $users->through(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'is_active' => $user->is_active, 'legacy_role' => $user->role->value,
            'roles' => $user->roles->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name, 'scope' => $role->pivot->data_scope]),
            'site_ids' => $user->schoolSites->pluck('id'),
            'effective_permissions' => $authorization->permissions($user),
        ]);

        return Inertia::render('Admin/Access/Users', $this->common() + ['users' => $users, 'filters' => $request->only('search')]);
    }

    public function roles(): Response
    {
        return Inertia::render('Admin/Access/Roles', $this->common());
    }

    public function matrix(): Response
    {
        return Inertia::render('Admin/Access/PermissionsMatrix', $this->common() + ['permissionModules' => $this->permissionModules()]);
    }

    public function history(Request $request): Response
    {
        $history = AuditLog::with(['user:id,name', 'related'])->where('event', 'like', 'rbac.%')
            ->when($request->string('search')->trim()->toString(), fn ($q, $search) => $q->where(fn ($q) => $q->where('event', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->latest('occurred_at')->paginate(25)->withQueryString();

        return Inertia::render('Admin/Access/History', ['history' => $history, 'filters' => $request->only('search')]);
    }

    private function common(): array
    {
        return [
            'roles' => Role::with('permissions:id,key')->withCount('users')->orderBy('name')->get(),
            'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'dataScopes' => ['tenant', 'site', 'assigned', 'own'],
        ];
    }

    private function permissionModules(): array
    {
        return collect(PermissionCatalog::all())->groupBy(fn ($label, $key) => str($key)->before('.')->toString())
            ->map(fn ($items, $module) => ['key' => $module, 'label' => str($module)->replace('_', ' ')->title()->toString(),
                'permissions' => $items->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'action' => $this->actionFor($key)])->values()])->values()->all();
    }

    private function actionFor(string $key): string
    {
        $action = str($key)->afterLast('.')->toString();
        return match ($action) { 'view' => 'view', 'create' => 'create', 'update' => 'edit', 'delete' => 'delete', 'export' => 'export', 'approve' => 'approve', default => 'manage' };
    }
}
