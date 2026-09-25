<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TrialExpiredController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant?->demoExpired() && ! $tenant->hasActiveSubscription(), 404);

        return Inertia::render('auth/TrialExpired', [
            'school' => $tenant->only(['name', 'trial_started_at', 'demo_expires_at']),
            'contact' => ['email' => config('saas.support_email'), 'phone' => config('saas.support_phone')],
        ]);
    }
}
