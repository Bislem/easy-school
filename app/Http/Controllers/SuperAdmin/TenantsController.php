<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TenantInitializer;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class TenantsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ]);
        $schools = Tenant::query()->with('subscriptionPlan:id,name,price,currency,billing_period')->withCount(['users', 'students', 'staff'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(12)->withQueryString();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'schools' => $schools,
            'filters' => $filters,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'summary' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
                'monthly_revenue' => SubscriptionPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request, TenantInitializer $initializer): RedirectResponse
    {
        $data = $request->validate($this->schoolRules() + [
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', Password::defaults()],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'plan_expires_at' => ['nullable', 'date', 'after:today'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $logoPath = null;

        try {
            $school = DB::transaction(function () use ($request, $data, $initializer, &$logoPath) {
                $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
                $expiresAt = $data['plan_expires_at'] ?? match ($plan->billing_period) {
                    'monthly' => now()->addMonth(), 'yearly' => now()->addYear(), default => null,
                };
                $school = Tenant::create([
                    ...collect($data)->only(['name', 'email', 'phone', 'address', 'wilaya', 'commune'])->all(),
                    'slug' => $this->uniqueSlug($data['name']), 'status' => 'active', 'account_type' => 'paid',
                    'subscription_plan_id' => $plan->id, 'plan_started_at' => now(), 'plan_expires_at' => $expiresAt,
                ]);
                app(TenantContext::class)->set($school);
                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store("tenants/{$school->id}/branding", 'public');
                    $school->update(['logo' => $logoPath]);
                }
                $admin = User::create([
                    'name' => $data['admin_name'], 'email' => Str::lower($data['admin_email']),
                    'phone' => $data['admin_phone'] ?? null, 'password' => $data['password'],
                    'role' => UserRole::ADMIN, 'is_active' => true, 'can_login' => true,
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
                $initializer->initialize($school);
                if (($data['payment_amount'] ?? null) !== null) {
                    $proofPath = $request->file('payment_proof')?->store("subscription-payments/{$school->id}", 'local');
                    SubscriptionPayment::create([
                        'tenant_id' => $school->id, 'subscription_plan_id' => $plan->id,
                        'amount' => $data['payment_amount'], 'currency' => $plan->currency,
                        'paid_at' => now()->toDateString(), 'payment_method' => $data['payment_method'] ?? null,
                        'reference' => $data['payment_reference'] ?? null, 'proof_path' => $proofPath,
                        'type' => 'initial', 'recorded_by' => auth('super_admin')->id(),
                    ]);
                }

                return $school;
            });
        } catch (Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            throw $exception;
        } finally {
            app(TenantContext::class)->clear();
        }

        return redirect()->route('super-admin.tenants.show', $school)->with('success', 'École créée.');
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load('subscriptionPlan')->loadCount(['users', 'students', 'staff']);
        $administrator = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)->oldest()->first();

        $stats = [
            'students' => DB::table('students')->where('tenant_id', $tenant->id)->count(),
            'staff' => DB::table('staff')->where('tenant_id', $tenant->id)->count(),
            'users' => DB::table('users')->where('tenant_id', $tenant->id)->count(),
            'sites' => DB::table('school_sites')->where('tenant_id', $tenant->id)->count(),
            'rooms' => DB::table('classrooms')->where('tenant_id', $tenant->id)->count(),
            'courses' => DB::table('courses')->where('tenant_id', $tenant->id)->count(),
            'plans' => DB::table('training_plans')->where('tenant_id', $tenant->id)->count(),
            'sessions' => DB::table('training_sessions')->where('tenant_id', $tenant->id)->count(),
            'revenue' => DB::table('student_payments')->where('tenant_id', $tenant->id)->where('status', 'completed')->sum('amount'),
            'subscription_paid' => SubscriptionPayment::where('tenant_id', $tenant->id)->sum('amount'),
        ];

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'school' => $tenant, 'administrator' => $administrator, 'stats' => $stats,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'payments' => SubscriptionPayment::with('plan:id,name')->where('tenant_id', $tenant->id)->latest('paid_at')->get(),
        ]);
    }

    public function subscription(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['renew', 'extend', 'change'])],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'months' => ['required', 'integer', 'min:1', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $base = $data['action'] === 'extend' && $tenant->plan_expires_at?->isFuture() ? $tenant->plan_expires_at->copy() : now();
        $tenant->update([
            'subscription_plan_id' => $data['subscription_plan_id'], 'plan_started_at' => $data['action'] === 'change' ? now() : ($tenant->plan_started_at ?? now()),
            'plan_expires_at' => $base->addMonths($data['months'])->endOfDay(), 'status' => 'active', 'account_type' => 'paid',
        ]);
        if (($data['amount'] ?? null) !== null) {
            $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'amount' => $data['amount'],
                'currency' => $plan->currency, 'paid_at' => now()->toDateString(), 'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'type' => $data['action'],
                'proof_path' => $request->file('proof')?->store("subscription-payments/{$tenant->id}", 'local'),
                'recorded_by' => auth('super_admin')->id(),
            ]);
        }

        return back()->with('success', 'Abonnement client mis à jour.');
    }

    public function paymentProof(Tenant $tenant, SubscriptionPayment $payment): StreamedResponse
    {
        abort_unless($payment->tenant_id === $tenant->id && $payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);
        return Storage::disk('local')->download($payment->proof_path);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate($this->schoolRules());
        if ($request->hasFile('logo')) {
            $oldLogo = $tenant->logo;
            $data['logo'] = $request->file('logo')->store("tenants/{$tenant->id}/branding", 'public');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
        }
        $tenant->update($data);

        return back()->with('success', 'École mise à jour.');
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $tenant->update($data);

        return back()->with('success', $data['status'] === 'active' ? 'École activée.' : 'École suspendue.');
    }

    public function administrator(Request $request, Tenant $tenant): RedirectResponse
    {
        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', UserRole::ADMIN->value)->oldest()->first();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($admin?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [$admin ? 'nullable' : 'required', Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($admin) {
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            $admin->update($data + ['can_login' => true]);
        } else {
            app(TenantContext::class)->set($tenant);
            try {
                $admin = User::create($data + ['role' => UserRole::ADMIN, 'can_login' => true]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        return back()->with('success', 'Administrateur principal mis à jour.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('super-admin.tenants.index')->with('success', 'École archivée.');
    }

    private function schoolRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:500'],
            'wilaya' => ['nullable', 'string', 'max:100'], 'commune' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
