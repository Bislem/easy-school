<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use App\Enums\UserRole;
use App\Models\CompanySetting;

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

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();
        $role = $user->getRawOriginal('role');

        if (! in_array($role, [UserRole::ADMIN->value, UserRole::TEACHER->value, UserRole::EMPLOYEE->value], true) || ! $user->can_login) {
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
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
