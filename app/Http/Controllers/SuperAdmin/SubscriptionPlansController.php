<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlansController extends Controller
{
    public function index(): Response
    {
        $plans = SubscriptionPlan::withCount('tenants')
            ->orderBy('sort_order')
            ->get()
            ->each(fn (SubscriptionPlan $plan) => $plan->setAttribute(
                'storage_go',
                $plan->storage_mb ? round($plan->storage_mb / 1024, 2) : null,
            ));

        return Inertia::render('SuperAdmin/Plans/Index', ['plans' => $plans, 'schools' => Tenant::with('subscriptionPlan:id,name')->orderBy('name')->get(['id', 'name', 'subscription_plan_id', 'plan_started_at', 'plan_expires_at'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['storage_mb'] = $this->storageInMegabytes($data);
        unset($data['storage_go']);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['features'] = $this->features($data['features'] ?? null);
        SubscriptionPlan::create($data);

        return back()->with('success', 'Plan créé.');
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $this->validated($request);
        $data['storage_mb'] = $this->storageInMegabytes($data);
        unset($data['storage_go']);
        $data['features'] = $this->features($data['features'] ?? null);
        $plan->update($data);

        return back()->with('success', 'Plan mis à jour.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->tenants()->exists()) {
            return back()->withErrors(['plan' => 'Ce plan est attribué à des écoles.']);
        } $plan->delete();

        return back()->with('success', 'Plan supprimé.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate(['tenant_id' => ['required', 'exists:tenants,id'], 'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'], 'plan_expires_at' => ['nullable', 'date', 'after:today']]);
        $tenant = Tenant::findOrFail($data['tenant_id']);
        $tenant->update(['subscription_plan_id' => $data['subscription_plan_id'] ?: null, 'plan_started_at' => $data['subscription_plan_id'] ? now() : null, 'plan_expires_at' => $data['subscription_plan_id'] ? ($data['plan_expires_at'] ?? null) : null]);

        return back()->with('success', 'Abonnement mis à jour.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'price' => ['required', 'numeric', 'min:0'], 'currency' => ['required', Rule::in(['DZD', 'EUR', 'USD'])], 'billing_period' => ['required', Rule::in(['monthly', 'yearly', 'custom'])], 'max_students' => ['nullable', 'integer', 'min:1'], 'max_teachers' => ['nullable', 'integer', 'min:1'], 'max_staff' => ['nullable', 'integer', 'min:1'], 'max_sites' => ['nullable', 'integer', 'min:1'], 'max_users' => ['nullable', 'integer', 'min:1'], 'max_courses' => ['nullable', 'integer', 'min:1'], 'storage_go' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'], 'storage_mb' => ['nullable', 'integer', 'min:1'], 'features' => ['nullable', 'string', 'max:5000'], 'is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:999']]);
    }

    private function storageInMegabytes(array $data): ?int
    {
        if (array_key_exists('storage_go', $data) && $data['storage_go'] !== null) {
            return (int) round((float) $data['storage_go'] * 1024);
        }

        return isset($data['storage_mb']) ? (int) $data['storage_mb'] : null;
    }

    private function features(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))->map(fn ($item) => trim($item))->filter()->unique()->values()->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 2;
        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
