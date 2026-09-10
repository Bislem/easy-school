<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantStorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSubscriptionController extends Controller
{
    public function __invoke(Request $request, TenantStorageService $storage): Response
    {
        $tenant = $request->user()->tenant()->with('subscriptionPlan')->firstOrFail();
        $expiry = $tenant->isDemo() ? $tenant->demo_expires_at : $tenant->plan_expires_at;
        $status = $tenant->status !== 'active' ? 'suspended' : ($expiry?->isPast() ? 'expired' : ($tenant->isDemo() ? 'demo' : 'active'));
        $plan = $tenant->subscriptionPlan;

        return Inertia::render('Admin/Account/Index', [
            'account' => [
                'name' => $tenant->name, 'status' => $status, 'account_type' => $tenant->account_type,
                'plan_started_at' => $tenant->plan_started_at, 'expires_at' => $expiry,
                'remaining_days' => $expiry ? max(0, now()->startOfDay()->diffInDays($expiry, false)) : null,
            ],
            'storage' => $storage->summary($tenant),
            'plan' => $plan ? [
                'name' => $plan->name, 'description' => $plan->description, 'billing_period' => $plan->billing_period,
                'limits' => collect(['students' => $plan->max_students, 'teachers' => $plan->max_teachers, 'staff' => $plan->max_staff, 'sites' => $plan->max_sites, 'users' => $plan->max_users, 'courses' => $plan->max_courses, 'storage_bytes' => $tenant->storage_limit_bytes])->filter(fn ($value) => $value !== null),
                'features' => $plan->features ?? [],
            ] : null,
        ]);
    }
}
