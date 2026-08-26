<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EmployeeType;
use App\Models\Staff;
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
        $base = User::query()->whereIn('role', [UserRole::ADMIN->value, UserRole::TEACHER->value, UserRole::EMPLOYEE->value]);
        $stats = ['total' => (clone $base)->count(), 'active' => (clone $base)->where('is_active', true)->count(), 'teachers' => (clone $base)->where('role', UserRole::TEACHER)->count(), 'employees' => (clone $base)->where('role', UserRole::EMPLOYEE)->count()];
        $users = $base->with(['staff:id,user_id,employee_type_id,employee_code,employment_status,hire_date,photo_path,social_security_number', 'staff.employeeType:id,name,is_teacher'])
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn ($staff) => $staff->where('employee_code', 'like', "%{$search}%")->orWhere('social_security_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->string('role')->toString(), fn ($query, string $role) => $query->where('role', $role))
            ->when($request->string('employee_type')->toString(), fn ($query, string $type) => $query->whereHas('staff', fn ($staff) => $staff->where('employee_type_id', $type)))
            ->when($request->string('employment_status')->toString(), fn ($query, string $status) => $query->whereHas('staff', fn ($staff) => $staff->where('employment_status', $status)))
            ->when($request->string('access')->toString(), function ($query, string $access) {
                match ($access) {
                    'enabled' => $query->where('can_login', true), 'disabled' => $query->where('can_login', false), 'inactive' => $query->where('is_active', false), default => null
                };
            })
            ->latest()
            ->paginate(18)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'employee_type', 'employment_status', 'access']),
            'employeeTypes' => EmployeeType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);

        if (blank($validated['password'] ?? null)) {
            $validated['password'] = str()->password(32);
        }

        $user = User::create($validated + [
            'email_verified_at' => now(),
        ]);
        $staff = $this->syncStaff($user);

        if ($staff) {
            return to_route('admin.staff.edit', $staff)->with('success', 'Compte créé. Complétez maintenant le dossier RH de l’employé.');
        }

        return back()->with('success', 'Utilisateur créé avec succès.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        if ($request->user()->is($user)
            && (! $validated['is_active'] || ! $validated['can_login'] || $validated['role'] !== UserRole::ADMIN->value)) {
            return back()->withErrors([
                'user' => 'Vous ne pouvez pas désactiver ou retirer votre propre rôle administrateur.',
            ]);
        }

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $user->update($validated);
        $this->syncStaff($user);

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'job_title' => ['nullable', 'required_if:role,employee', 'string', 'max:150'],
            'can_login' => ['sometimes', 'boolean'],
            'password' => [Rule::requiredIf($user === null && $request->boolean('can_login')), 'nullable', 'confirmed', Password::defaults()],
        ]);

        $validated['can_login'] = array_key_exists('can_login', $validated)
            ? (bool) $validated['can_login']
            : $validated['role'] !== UserRole::EMPLOYEE->value;

        return $validated;
    }

    private function syncStaff(User $user): ?Staff
    {
        if (! in_array($user->role, [UserRole::TEACHER, UserRole::EMPLOYEE], true)) {
            return null;
        }
        $type = EmployeeType::where('slug', $user->role === UserRole::TEACHER ? 'teacher' : 'other')->firstOrFail();
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return Staff::updateOrCreate(['user_id' => $user->id], [
            'employee_type_id' => $type->id, 'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
            'email' => $user->email, 'phone' => $user->phone, 'birth_date' => $user->birth_date,
            'employment_status' => $user->is_active ? 'active' : 'inactive',
            'employee_code' => 'EMP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
        ]);
    }
}
