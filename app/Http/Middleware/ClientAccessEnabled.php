<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientAccessEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            CompanySetting::current()->client_login_disabled,
            403,
            'Client login and portal access are currently disabled.',
        );

        return $next($request);
    }
}
