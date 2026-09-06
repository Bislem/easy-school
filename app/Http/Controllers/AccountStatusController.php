<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = $request->user()->tenant()->with('subscriptionPlan:id,name,price,currency,billing_period')->firstOrFail();

        return Inertia::render('auth/AccountPending', [
            'school' => $tenant->only(['name', 'status', 'registration_submitted_at', 'registration_rejection_reason']),
            'plan' => $tenant->subscriptionPlan,
            'contact' => [
                'email' => config('saas.support_email'),
                'phone' => config('saas.support_phone'),
                'hours' => config('saas.support_hours'),
            ],
        ]);
    }
}
