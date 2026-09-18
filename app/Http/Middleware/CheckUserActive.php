<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $tenant = $request->attributes->get('tenant') ?? $user?->tenant;
        if (Auth::check() && in_array($tenant?->status, ['pending', 'rejected'], true)) {
            return redirect()->route('account.pending');
        }

        if (Auth::check() && (! $user->is_active || $user->can_login === false || $tenant?->status !== 'active' || $tenant?->demoExpired())) {
            Auth::logout();
            
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return redirect()->route($user?->role === UserRole::PARENT ? 'parent.login' : 'login')
                ->with('error', $tenant?->demoExpired() ? 'Votre démonstration est terminée.' : "Votre accès au portail a été désactivé. Veuillez contacter l'administrateur.");
        }

        return $next($request);
    }
}
