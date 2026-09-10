<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireEffectivePermission
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $permission ??= $this->authorization->permissionForRoute($request->route()?->getName(), $request->method());
        abort_unless($permission && $request->user() && $this->authorization->allows($request->user(), $permission), 403, 'Forbidden.');

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) $this->authorization->authorizeRecord($request->user(), $permission, $parameter);
        }
        $request->attributes->set('effective_permission', $permission);
        return $next($request);
    }
}
