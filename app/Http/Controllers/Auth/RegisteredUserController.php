<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => ['required', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'birth_date' => ['required', 'date', 'before:today'],
            'driving_license_number' => ['required', 'string', 'max:100', 'unique:users,driving_license_number'],
            'driving_license_delivered_at' => ['required', 'date', 'before_or_equal:today', 'after:birth_date'],
            'driving_license_authority' => ['required', 'string', 'max:255'],
            'driving_license_copy' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $licensePath = $request->file('driving_license_copy')->store('driving-licenses', 'public');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::CLIENT,
            'birth_date' => $validated['birth_date'],
            'driving_license_number' => $validated['driving_license_number'],
            'driving_license_delivered_at' => $validated['driving_license_delivered_at'],
            'driving_license_authority' => $validated['driving_license_authority'],
            'driving_license_path' => $licensePath,
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        event(new Registered($user));

        return to_route('login')->with('status', "Votre compte a été créé et attend l'approbation de l'agence. Vous pourrez vous connecter après validation de votre permis de conduire.");
    }
}
