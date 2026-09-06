<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('super_admin')->user();

        if (! $user) {
            return redirect()->route('super-admin.login');
        }

        if ($user->tenant_id !== null || $user->getRawOriginal('role') !== UserRole::SUPER_ADMIN->value) {
            abort(403, 'Accès réservé à l’administration de la plateforme.');
        }

        return $next($request);
    }
}
