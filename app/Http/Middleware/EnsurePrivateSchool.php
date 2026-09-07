<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class EnsurePrivateSchool
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->tenant?->organization_type === 'private_school', 404);
        return $next($request);
    }
}
