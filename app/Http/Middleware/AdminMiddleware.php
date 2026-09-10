<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // This middleware now identifies the tenant back-office boundary only.
        // Fine-grained access is enforced by RequireEffectivePermission.
        if (Auth::check() && Auth::user()->tenant_id && Auth::user()->role !== UserRole::SUPER_ADMIN) {
            return $next($request);
        }

        abort(403, 'Forbidden.');
    }
}
