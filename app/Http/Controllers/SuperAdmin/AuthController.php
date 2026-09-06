<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

final class AuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('super_admin')->user()?->getRawOriginal('role') === UserRole::SUPER_ADMIN->value) {
            return redirect()->route('super-admin.dashboard');
        }

        return Inertia::render('SuperAdmin/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['boolean'],
        ]);
        $user = User::withoutGlobalScopes()
            ->where('email', strtolower($credentials['email']))
            ->whereNull('tenant_id')
            ->where('role', UserRole::SUPER_ADMIN->value)
            ->first();

        if (! $user || ! $user->is_active || ! $user->can_login || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Identifiants super administrateur invalides.'])->onlyInput('email');
        }

        Auth::guard('super_admin')->login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->route('super-admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('super_admin')->logout();
        // Keep any independent school login stored by the web guard.
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }
}
