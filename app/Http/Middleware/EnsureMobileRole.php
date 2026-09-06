<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->attributes->get('mobile_role') !== $role) return response()->json(['message' => 'Vous n’êtes pas autorisé à accéder à cette ressource.', 'error' => 'forbidden_role'], 403);
        return $next($request);
    }
}
