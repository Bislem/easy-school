<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\MobileMembership;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);

        // Platform administration deliberately runs without a school context,
        // even when the same browser also has an active school login.
        $superAdminPath = trim((string) config('saas.super_admin_path'), '/');
        if ($superAdminPath !== '' && $request->is($superAdminPath.'/*')) {
            $context->clear();

            return $next($request);
        }

        $user = $request->user();
        $tenant = $user?->tenant;

        if ($user?->role === UserRole::PARENT && ! $tenant) {
            $membershipQuery = MobileMembership::with('tenant')
                ->where('user_id', $user->id)
                ->where('role', UserRole::PARENT->value)
                ->where('is_active', true);
            $selectedTenantId = (int) $request->session()->get('parent.tenant_id');
            $membership = $selectedTenantId
                ? (clone $membershipQuery)->where('tenant_id', $selectedTenantId)->first()
                : null;
            $membership ??= $membershipQuery->first();
            $tenant = $membership?->tenant;

            if ($tenant) {
                $request->session()->put('parent.tenant_id', $tenant->id);
            }
        }

        if ($tenant) {
            $context->set($tenant);
            $request->attributes->set('tenant', $tenant);
            config([
                'vilt-filepond.temp_path' => 'tenants/'.$context->id().'/temp-files',
                'vilt-filepond.files_path' => 'tenants/'.$context->id().'/files',
            ]);
        }

        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
