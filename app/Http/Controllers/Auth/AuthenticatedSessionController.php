<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\CompanySetting;
use App\Models\MobileMembership;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function createParent(Request $request): Response
    {
        return Inertia::render('Parent/Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        return $this->authenticate($request, false);
    }

    public function storeParent(LoginRequest $request): SymfonyResponse
    {
        return $this->authenticate($request, true);
    }

    private function authenticate(LoginRequest $request, bool $parentPortal): SymfonyResponse
    {
        $user = $request->validateCredentials();
        $role = $user->getRawOriginal('role');

        if ($parentPortal && $role !== UserRole::PARENT->value) {
            return back()->withErrors([
                'email' => 'Cet espace est réservé aux comptes parents.',
            ])->onlyInput('email');
        }

        if (! $parentPortal && $role === UserRole::PARENT->value) {
            return back()->withErrors([
                'email' => 'Veuillez utiliser l’espace de connexion réservé aux parents.',
            ])->onlyInput('email');
        }

        $tenant = $parentPortal
            ? MobileMembership::with('tenant')
                ->where('user_id', $user->id)
                ->where('role', UserRole::PARENT->value)
                ->where('is_active', true)
                ->first()?->tenant
            : $user->tenant;

        // Older parent accounts were tenant-bound before parent identities
        // became global. Keep those accounts working during the transition.
        $tenant ??= $parentPortal && $user->tenant_id
            ? Tenant::find($user->tenant_id)
            : null;

        if (! $tenant) {
            return back()->withErrors([
                'email' => $parentPortal
                    ? 'Aucun établissement actif n’est associé à ce compte parent.'
                    : "Aucun établissement n’est associé à ce compte.",
            ])->onlyInput('email');
        }

        app(TenantContext::class)->set($tenant);
        if ($parentPortal) {
            $request->session()->put('parent.tenant_id', $tenant->id);
        }

        if (in_array($tenant->status, ['pending', 'rejected'], true)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return Inertia::location(route('account.pending', absolute: false));
        }

        if ($tenant->status !== 'active') {
            Auth::logout();

            return back()->withErrors(['email' => "L'accès de cette école est actuellement désactivé."])->onlyInput('email');
        }

        if ($tenant->demoExpired()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Votre période de démonstration est terminée. Contactez-nous pour activer votre abonnement.'])->onlyInput('email');
        }

        if (! in_array($role, [UserRole::ADMIN->value, UserRole::TEACHER->value, UserRole::EMPLOYEE->value, UserRole::STUDENT->value, UserRole::PARENT->value], true) || ! $user->can_login) {
            Auth::logout();

            return back()->withErrors([
                'email' => "Ce compte n'est pas autorisé à accéder au portail.",
            ])->onlyInput('email');
        }

        if ($role === UserRole::TEACHER->value && CompanySetting::current()->teacher_login_disabled) {
            Auth::logout();

            return back()->withErrors([
                'email' => "L'accès des enseignants est actuellement désactivé par l'administrateur.",
            ])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => "Votre compte est inactif. Veuillez contacter l'administrateur."])->onlyInput('email');
        }

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            if ($parentPortal) {
                $request->session()->put('url.intended', route('parent.dashboard', absolute: false));
            }
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $destination = $parentPortal
            ? redirect()->intended(route('parent.dashboard', absolute: false))
            : redirect()->intended(route('dashboard', absolute: false));

        // Authentication regenerates the session. Force a full navigation for
        // Inertia logins so the dashboard request always uses the new cookie.
        return Inertia::location($destination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $wasParent = $request->user()?->getRawOriginal('role') === UserRole::PARENT->value;
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($wasParent ? 'parent.login' : 'home');
    }
}
