<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantInitializer;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class TenantRegistrationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/RegisterSchool');
    }

    public function store(Request $request, TenantInitializer $initializer): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'admin_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'organization_type' => ['required', 'in:private_school,training_center,language_school'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            [$tenant, $admin] = DB::transaction(function () use ($data, $initializer) {
                $trialStartedAt = now();
                $tenant = Tenant::create([
                    'name' => $data['name'], 'slug' => $this->uniqueSlug($data['name']),
                    'phone' => $data['phone'] ?? null, 'email' => Str::lower($data['email']),
                    'status' => 'active', 'account_type' => 'demo',
                    'organization_type' => $data['organization_type'],
                    'trial_started_at' => $trialStartedAt,
                    'demo_expires_at' => $trialStartedAt->copy()->addDays(30),
                    'subscription_plan_id' => null,
                    'registration_submitted_at' => now(),
                ]);
                app(TenantContext::class)->set($tenant);

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
            app(TenantContext::class)->clear();
            throw $exception;
        }

        Auth::login($admin);
        $request->session()->regenerate();
        app(TenantContext::class)->set($tenant);

        return redirect()->route('dashboard')->with('success', 'Votre école a été créée. Votre essai gratuit de 30 jours est actif.');
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
