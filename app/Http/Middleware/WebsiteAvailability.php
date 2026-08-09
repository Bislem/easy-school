<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\CompanySetting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class WebsiteAvailability
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = CompanySetting::current();

        if (!$settings->website_disabled || $this->canAccessAdministration($request)) {
            return $next($request);
        }

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            abort(403, 'The public website is currently unavailable.');
        }

        return Inertia::render('PublicWebsiteDisabled', [
            'agency' => $settings,
        ])->toResponse($request);
    }

    private function canAccessAdministration(Request $request): bool
    {
        if ($request->user()?->role === UserRole::ADMIN) {
            return true;
        }

        return $request->is('admin-secret-url')
            || $request->is('two-factor-challenge')
            || $request->is('logout');
    }
}
