<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MobileMembership;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PortalAccountsController extends Controller
{
    public function student(Request $request, Student $student): RedirectResponse
    {
        abort_if($student->user_id, 422);
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8']]);
        DB::transaction(function () use ($student, $data) {
            $user = $this->account($data['email'], $data['password'], $student->full_name, UserRole::STUDENT);
            $student->update(['user_id' => $user->id, 'email' => $student->email ?: $data['email']]);
        });
        return back()->with('success', 'Accès étudiant créé.');
    }

    public function parent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'], 'phone' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:100'], 'password' => ['required', 'string', 'min:8'],
            'student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer', Rule::exists('students', 'id')],
        ]);
        DB::transaction(function () use ($data) {
            $user = $this->account($data['email'], $data['password'], $data['first_name'].' '.$data['last_name'], UserRole::PARENT);
            $parent = SchoolParent::create([...$data, 'user_id' => $user->id]);
            $parent->students()->sync($data['student_ids']);
            MobileMembership::updateOrCreate(
                ['user_id' => $user->id, 'tenant_id' => app(TenantContext::class)->id(), 'role' => UserRole::PARENT->value],
                ['parent_id' => $parent->id, 'is_active' => true],
            );
        });
        return back()->with('success', 'Compte parent créé.');
    }

    public function children(Request $request, SchoolParent $parent): RedirectResponse
    {
        $ids = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer', Rule::exists('students', 'id')]])['student_ids'];
        $parent->students()->sync($ids);
        return back()->with('success', 'Enfants autorisés mis à jour.');
    }

    private function account(string $email, string $password, string $name, UserRole $role): User
    {
        $user = User::withoutGlobalScopes()->where('email', str($email)->lower())->first();
        if ($user) return $user;
        return User::create(['name' => $name, 'email' => str($email)->lower(), 'password' => $password, 'role' => $role, 'is_active' => true, 'can_login' => true, 'email_verified_at' => now()]);
    }
}
