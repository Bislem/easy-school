<?php

namespace App\Http\Middleware;

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

        if ($request->user()?->tenant_id) {
            $context->set($request->user()->tenant_id);
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
