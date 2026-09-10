<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TenantInitializer;
use App\Services\TenantStorageService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TenantRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/RegisterSchool', [
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'description', 'price', 'currency', 'billing_period', 'features', 'max_students', 'max_sites']),
        ]);
    }

    public function store(Request $request, TenantInitializer $initializer, TenantStorageService $tenantStorage): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'admin_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'wilaya' => ['nullable', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'payment_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        abort_unless(SubscriptionPlan::whereKey($data['subscription_plan_id'])->where('is_active', true)->exists(), 422, 'Le plan sélectionné n’est plus disponible.');

        $logoPath = null;
        $paymentProofPath = null;
        try {
            [$tenant, $admin] = DB::transaction(function () use ($request, $data, $initializer, $tenantStorage, &$logoPath, &$paymentProofPath) {
                $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
                $tenant = Tenant::create([
                    'name' => $data['name'], 'slug' => $this->uniqueSlug($data['name']),
                    'phone' => $data['phone'] ?? null, 'email' => Str::lower($data['email']),
                    'address' => $data['address'] ?? null, 'wilaya' => $data['wilaya'] ?? null,
                    'commune' => $data['commune'] ?? null, 'status' => 'pending',
                    'subscription_plan_id' => $data['subscription_plan_id'],
                    'storage_limit_bytes' => $plan->storage_mb === null ? null : $plan->storage_mb * 1024 * 1024,
                    'registration_submitted_at' => now(),
                ]);
                app(TenantContext::class)->set($tenant);

                if ($request->hasFile('logo')) {
                    $logoPath = $tenantStorage->store($request->file('logo'), 'branding', 'public', TenantStorageService::PROFILE_IMAGES, 'school_branding', $tenant, $tenant);
                    $tenant->update(['logo' => $logoPath]);
                }
                $paymentProofPath = $tenantStorage->store($request->file('payment_proof'), 'registration', 'local', TenantStorageService::ATTACHMENTS, 'registration', $tenant, $tenant);
                $tenant->update(['payment_proof_path' => $paymentProofPath]);

                $admin = User::create([
                    'name' => $data['admin_name'], 'email' => Str::lower($data['email']),
                    'phone' => $data['phone'] ?? null, 'password' => $data['password'],
                    'role' => UserRole::ADMIN, 'is_active' => true, 'can_login' => true,
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
                $initializer->initialize($tenant);

                return [$tenant, $admin];
            });
        } catch (Throwable $exception) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            if ($paymentProofPath) {
                Storage::disk('local')->delete($paymentProofPath);
            }
            app(TenantContext::class)->clear();
            throw $exception;
        }

        Auth::login($admin);
        $request->session()->regenerate();
        app(TenantContext::class)->set($tenant);

        return redirect()->route('account.pending')->with('success', 'Votre demande a été envoyée avec succès.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
