<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->whereIn('role', [UserRole::ADMIN->value, UserRole::TEACHER->value])
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->string('role')->toString(), fn ($query, string $role) => $query->where('role', $role))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        User::create($validated + [
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        if ($request->user()->is($user)
            && (! $validated['is_active'] || $validated['role'] !== UserRole::ADMIN->value)) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas désactiver ou retirer votre propre rôle administrateur.',
            ]);
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);

        return back()->with('success', 'Utilisateur mis à jour avec succès.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active
            ? 'Le compte a été activé.'
            : 'Le compte a été désactivé.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => [Rule::requiredIf($user === null), 'nullable', 'confirmed', Password::defaults()],
        ]);
    }
}
