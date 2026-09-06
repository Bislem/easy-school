<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->attributes->get('mobile_role') !== 'parent') return response()->json(['message' => 'Seuls les comptes parents peuvent utiliser l’application mobile.', 'error' => 'role_not_allowed'], 403);
        if (! $user->is_active || ! $user->can_login) return response()->json(['message' => 'Votre accès est désactivé. Contactez votre établissement.', 'error' => 'account_inactive'], 403);
        return $next($request);
    }
}
