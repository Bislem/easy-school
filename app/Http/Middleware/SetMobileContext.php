<?php

namespace App\Http\Middleware;

use App\Models\MobileMembership;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetMobileContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $abilities = $request->user()?->currentAccessToken()?->abilities ?? [];
        $membershipId = collect($abilities)->first(fn ($ability) => str_starts_with($ability, 'membership:'));
        $membershipId = $membershipId ? (int) str($membershipId)->after('membership:')->value() : null;
        $membership = $membershipId ? MobileMembership::with('tenant')->where('user_id', $request->user()->id)->where('role', 'parent')->where('is_active', true)->find($membershipId) : null;

        // Compatibility for tokens created before multi-school mobile accounts existed.
        $isLegacyToken = $abilities === ['mobile'] || $request->user()->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken;
        if (! $membership && ! $membershipId && $isLegacyToken && $request->user()->tokenCan('mobile') && $request->user()->role?->value === 'parent' && $request->user()->tenant_id) {
            $membership = new MobileMembership(['user_id' => $request->user()->id, 'tenant_id' => $request->user()->tenant_id, 'role' => $request->user()->role, 'is_active' => true]);
            $membership->setRelation('tenant', $request->user()->tenant);
        }

        if (! $membership) {
            return response()->json(['message' => 'Sélectionnez un établissement et un rôle.', 'error' => 'mobile_context_required'], 409);
        }
        if (! $membership->tenant || $membership->tenant->status !== 'active' || $membership->tenant->demoExpired()) {
            return response()->json(['message' => 'Établissement indisponible.', 'error' => 'tenant_unavailable'], 403);
        }

        app(TenantContext::class)->set($membership->tenant_id);
        $request->attributes->set('mobile_membership', $membership);
        $request->attributes->set('mobile_role', $membership->role->value);
        try {
            return $next($request);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
