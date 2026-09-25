<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\SchoolCredentialsRegeneratedMail;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantInitializer;
use App\Services\TenantStorageService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $schools->getCollection()->each(function (Tenant $tenant): void {
            $expiry = $tenant->isDemo() ? $tenant->demo_expires_at : $tenant->plan_expires_at;
            $tenant->setAttribute('storage_percentage', $tenant->storage_limit_bytes ? min(100, round($tenant->storage_used_bytes / $tenant->storage_limit_bytes * 100, 1)) : 0);
            $tenant->setAttribute('account_status', $tenant->status !== 'active' ? 'suspended' : ($tenant->hasActiveSubscription() ? 'active' : ($tenant->demoExpired() ? 'expired' : ($tenant->isDemo() ? 'demo' : 'active'))));
            $tenant->setAttribute('remaining_days', $expiry ? max(0, now()->startOfDay()->diffInDays($expiry, false)) : null);
        });

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'schools' => $schools,
            'filters' => $filters,
            'plans' => SubscriptionPlan::whereNull('owner_tenant_id')->where('is_active', true)->orderBy('sort_order')->get(),
            'summary' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'suspended' => Tenant::where('status', 'suspended')->count(),
                'monthly_revenue' => SubscriptionPayment::whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            ],
        ]);
    }

    public function store(Request $request, TenantInitializer $initializer, TenantStorageService $tenantStorage): RedirectResponse
    {
        $data = $request->validate($this->schoolRules() + [
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', Password::defaults()],
            'subscription_plan_id' => ['required', Rule::exists('subscription_plans', 'id')->whereNull('owner_tenant_id')],
            'plan_expires_at' => ['nullable', 'date', 'after:today'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $logoPath = null;

        try {
            $school = DB::transaction(function () use ($request, $data, $initializer, $tenantStorage, &$logoPath) {
                $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
                $expiresAt = $data['plan_expires_at'] ?? match ($plan->billing_period) {
                    'monthly' => now()->addMonth(), 'yearly' => now()->addYear(), default => null,
                };
                $school = Tenant::create([
                    ...collect($data)->only(['name', 'email', 'phone', 'address', 'wilaya', 'commune'])->all(),
                    'slug' => $this->uniqueSlug($data['name']), 'status' => 'active', 'account_type' => 'paid',
                    'subscription_plan_id' => $plan->id, 'plan_started_at' => now(), 'plan_expires_at' => $expiresAt,
                    'storage_limit_bytes' => $plan->storage_mb === null ? null : $plan->storage_mb * 1024 * 1024,
                ]);
                app(TenantContext::class)->set($school);
                if ($request->hasFile('logo')) {
                    $logoPath = $tenantStorage->store($request->file('logo'), 'branding', 'public', TenantStorageService::PROFILE_IMAGES, 'school_branding', $school, $school);
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
                    $proofPath = $request->file('payment_proof') ? $tenantStorage->store($request->file('payment_proof'), 'subscription-payments', 'local', TenantStorageService::ATTACHMENTS, 'subscriptions', $school, $school) : null;
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

    public function show(Tenant $tenant, TenantStorageService $tenantStorage): Response
    {
        $tenant->load('subscriptionPlan')->loadCount(['users', 'students', 'staff']);
        $expiry = $tenant->isDemo() ? $tenant->demo_expires_at : $tenant->plan_expires_at;
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
            'plans' => SubscriptionPlan::where('is_active', true)->where(fn ($query) => $query->whereNull('owner_tenant_id')->orWhere('owner_tenant_id', $tenant->id))->orderBy('is_custom')->orderBy('sort_order')->get(),
            'payments' => SubscriptionPayment::with('plan:id,name')->where('tenant_id', $tenant->id)->latest('paid_at')->get(),
            'storage' => $tenantStorage->summary($tenant),
            'accountSummary' => [
                'status' => $tenant->status !== 'active' ? 'suspended' : ($tenant->hasActiveSubscription() ? 'active' : ($tenant->demoExpired() ? 'expired' : 'demo')),
                'trial_started_at' => $tenant->trial_started_at,
                'trial_expires_at' => $tenant->demo_expires_at,
                'expires_at' => $expiry,
                'remaining_days' => $tenant->isDemo() && $tenant->demo_expires_at ? max(0, now()->startOfDay()->diffInDays($tenant->demo_expires_at, false)) : null,
            ],
        ]);
    }

    public function subscription(Request $request, Tenant $tenant, TenantStorageService $tenantStorage): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['renew', 'extend', 'change'])],
            'plan_mode' => ['nullable', Rule::in(['existing', 'custom'])],
            'subscription_plan_id' => [Rule::requiredIf($request->input('plan_mode', 'existing') === 'existing'), 'nullable', 'exists:subscription_plans,id'],
            'custom_name' => [Rule::requiredIf($request->input('plan_mode') === 'custom'), 'nullable', 'string', 'max:100'],
            'custom_description' => ['nullable', 'string', 'max:1000'],
            'custom_price' => [Rule::requiredIf($request->input('plan_mode') === 'custom'), 'nullable', 'numeric', 'min:0'],
            'custom_currency' => [Rule::requiredIf($request->input('plan_mode') === 'custom'), 'nullable', Rule::in(['DZD', 'EUR', 'USD'])],
            'custom_billing_period' => [Rule::requiredIf($request->input('plan_mode') === 'custom'), 'nullable', Rule::in(['monthly', 'yearly', 'custom'])],
            'custom_max_students' => ['nullable', 'integer', 'min:1'], 'custom_max_teachers' => ['nullable', 'integer', 'min:1'],
            'custom_max_staff' => ['nullable', 'integer', 'min:1'], 'custom_max_sites' => ['nullable', 'integer', 'min:1'],
            'custom_max_users' => ['nullable', 'integer', 'min:1'], 'custom_max_courses' => ['nullable', 'integer', 'min:1'],
            'custom_storage_go' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'custom_features' => ['nullable', 'string', 'max:5000'],
            'months' => ['required', 'integer', 'min:1', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0'], 'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $data['plan_mode'] ??= 'existing';
        $plan = DB::transaction(function () use ($data, $tenant): SubscriptionPlan {
            if ($data['plan_mode'] === 'custom') {
                $plan = SubscriptionPlan::create([
                    'owner_tenant_id' => $tenant->id, 'is_custom' => true, 'is_active' => true,
                    'name' => $data['custom_name'], 'slug' => $this->uniquePlanSlug($tenant->name.' '.$data['custom_name']),
                    'description' => $data['custom_description'] ?? null, 'price' => $data['custom_price'],
                    'currency' => $data['custom_currency'], 'billing_period' => $data['custom_billing_period'],
                    'max_students' => $data['custom_max_students'] ?? null, 'max_teachers' => $data['custom_max_teachers'] ?? null,
                    'max_staff' => $data['custom_max_staff'] ?? null, 'max_sites' => $data['custom_max_sites'] ?? null,
                    'max_users' => $data['custom_max_users'] ?? null, 'max_courses' => $data['custom_max_courses'] ?? null,
                    'storage_mb' => isset($data['custom_storage_go']) ? (int) round((float) $data['custom_storage_go'] * 1024) : null,
                    'features' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['custom_features'] ?? '')))->map(fn ($feature) => trim($feature))->filter()->unique()->values()->all(),
                    'sort_order' => 0,
                ]);
            } else {
                $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
                abort_if($plan->owner_tenant_id !== null && (int) $plan->owner_tenant_id !== (int) $tenant->id, 422, 'Ce plan personnalisé appartient à un autre client.');
            }
            $base = $data['action'] === 'extend' && $tenant->plan_expires_at?->isFuture() ? $tenant->plan_expires_at->copy() : now();
            $tenant->update([
                'subscription_plan_id' => $plan->id, 'plan_started_at' => $data['action'] === 'change' || $data['plan_mode'] === 'custom' ? now() : ($tenant->plan_started_at ?? now()),
                'plan_expires_at' => $base->addMonths($data['months'])->endOfDay(), 'demo_expires_at' => null,
                'status' => 'active', 'account_type' => 'paid',
                'storage_limit_bytes' => $plan->storage_mb === null ? null : $plan->storage_mb * 1024 * 1024,
            ]);

            return $plan;
        });
        if (($data['amount'] ?? null) !== null) {
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'amount' => $data['amount'],
                'currency' => $plan->currency, 'paid_at' => now()->toDateString(), 'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null, 'type' => $data['action'],
                'proof_path' => $request->file('proof') ? $tenantStorage->store($request->file('proof'), 'subscription-payments', 'local', TenantStorageService::ATTACHMENTS, 'subscriptions', $tenant, $tenant) : null,
                'recorded_by' => auth('super_admin')->id(),
            ]);
        }

        return back()->with('success', 'Abonnement client mis à jour.');
    }

    private function uniquePlanSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan-personnalise';
        $slug = $base;
        $index = 2;
        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    public function paymentProof(Tenant $tenant, SubscriptionPayment $payment): StreamedResponse
    {
        abort_unless($payment->tenant_id === $tenant->id && $payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path);
    }

    public function update(Request $request, Tenant $tenant, TenantStorageService $tenantStorage): RedirectResponse
    {
        $data = $request->validate($this->schoolRules());
        if ($request->hasFile('logo')) {
            $oldLogo = $tenant->logo;
            $data['logo'] = $tenantStorage->store($request->file('logo'), 'branding', 'public', TenantStorageService::PROFILE_IMAGES, 'school_branding', $tenant, $tenant);
            if ($oldLogo) {
                $tenantStorage->delete($oldLogo, 'public', $tenant);
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

    public function regenerateCredentials(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'delivery_email' => ['nullable', 'email', 'max:255'],
        ]);
        $administrator = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('role', UserRole::ADMIN->value)
            ->oldest()
            ->firstOrFail();
        $recipient = Str::lower($data['delivery_email'] ?: $administrator->email);
        $password = Str::password(14, letters: true, numbers: true, symbols: false);

        DB::transaction(function () use ($administrator, $tenant, $recipient, $password): void {
            $administrator->update([
                'password' => $password,
                'is_active' => true,
                'can_login' => true,
            ]);
            $administrator->forceFill(['email_verified_at' => $administrator->email_verified_at ?? now()])->save();

            Mail::to($recipient)->send(new SchoolCredentialsRegeneratedMail(
                $tenant,
                $administrator->fresh(),
                $password,
            ));
        });

        return back()->with('success', "Identifiants régénérés et envoyés à {$recipient}.");
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
