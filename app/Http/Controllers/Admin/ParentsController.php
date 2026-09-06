<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MobileMembership;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;
use App\Services\Mobile\ParentAccountService;
use App\Services\NotificationDispatcher;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ParentsController extends Controller
{
    public function __construct(private ParentAccountService $accounts) {}

    public function index(Request $request): Response
    {
        $parents = SchoolParent::with([
            'user:id,name,first_name,last_name,email,phone,is_active,can_login',
            'mobileMembership:id,parent_id,is_active',
            'students:id,first_name,last_name,photo_path,school_level,status',
        ])->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where(fn ($query) => $query
            ->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->orWhereHas('user', fn ($user) => $user->withoutGlobalScope('tenant')->where('email', 'like', "%{$search}%"))
            ->orWhereHas('students', fn ($student) => $student->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Parents/Index', [
            'parents' => $parents,
            'students' => Student::orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'phone', 'photo_path', 'school_level']),
            'filters' => $request->only('search'),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->merge(['email' => str($request->input('email'))->lower()->toString()]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = User::withoutGlobalScopes()->where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['exists' => false]);
        }
        if ($user->role !== UserRole::PARENT) {
            return response()->json(['exists' => true, 'available' => false, 'message' => 'Cette adresse appartient à un compte qui n’est pas un compte parent.']);
        }

        $alreadyLinked = SchoolParent::where('user_id', $user->id)->exists();

        return response()->json([
            'exists' => true,
            'available' => ! $alreadyLinked,
            'already_linked' => $alreadyLinked,
            'message' => $alreadyLinked ? 'Ce parent est déjà ajouté à cet établissement.' : 'Compte parent existant : ses coordonnées seront copiées sans modification.',
            'parent' => ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => str($request->input('email'))->lower()->toString()]);
        $base = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', TenantRule::exists('students')],
        ]);
        $user = User::withoutGlobalScopes()->where('email', $base['email'])->first();
        $isNewAccount = ! $user;

        if ($user && $user->role !== UserRole::PARENT) {
            throw ValidationException::withMessages(['email' => 'Cette adresse appartient à un compte qui ne peut pas être utilisé comme parent.']);
        }
        if ($user && SchoolParent::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ce parent est déjà lié à cet établissement.']);
        }

        $profile = $isNewAccount ? $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]) : [];

        DB::transaction(function () use (&$user, $base, $profile, $isNewAccount): void {
            if ($isNewAccount) {
                $user = User::create([
                    'tenant_id' => null,
                    'name' => trim($profile['first_name'].' '.$profile['last_name']),
                    'first_name' => $profile['first_name'],
                    'last_name' => $profile['last_name'],
                    'email' => $base['email'],
                    'phone' => $profile['phone'] ?? null,
                    'password' => str()->random(64),
                    'role' => UserRole::PARENT,
                    'is_active' => true,
                    'can_login' => true,
                ]);
            }

            [$firstName, $lastName] = $this->parentNames($user);
            $parent = SchoolParent::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $user->phone,
                'relationship' => $base['relationship'] ?? null,
            ]);
            $this->syncChildren($parent, $base['student_ids']);
            MobileMembership::create([
                'user_id' => $user->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'role' => UserRole::PARENT,
                'parent_id' => $parent->id,
                'is_active' => true,
            ]);
        });

        if ($isNewAccount) {
            $this->accounts->issueTemporaryPassword($user, 'account_created');
        }
        foreach (Student::whereIn('id', $base['student_ids'])->get() as $student) {
            app(NotificationDispatcher::class)->send($user, 'parent.child_associated', 'Enfant associé à votre compte', $student->full_name.' a été associé(e) à votre espace parent.', $student, ['url' => '/portal/children/'.$student->id]);
        }

        return back()->with('success', $isNewAccount ? 'Compte parent créé. Le mot de passe temporaire a été envoyé par e-mail.' : 'Compte parent existant lié à l’établissement.');
    }

    public function update(Request $request, SchoolParent $parent): RedirectResponse
    {
        $data = $request->validate([
            'relationship' => ['nullable', 'string', 'max:100'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', TenantRule::exists('students')],
        ]);
        $added = collect($data['student_ids'])->diff($parent->students()->pluck('students.id'));
        DB::transaction(function () use ($data, $parent): void {
            $parent->update(['relationship' => $data['relationship'] ?? null]);
            $this->syncChildren($parent, $data['student_ids']);
        });
        foreach (Student::whereIn('id', $added)->get() as $student) {
            app(NotificationDispatcher::class)->send($parent->user, 'parent.child_associated', 'Enfant associé à votre compte', $student->full_name.' a été associé(e) à votre espace parent.', $student, ['url' => '/portal/children/'.$student->id]);
        }

        return back()->with('success', 'Accès du parent mis à jour.');
    }

    public function toggle(SchoolParent $parent): RedirectResponse
    {
        $membership = $parent->mobileMembership;
        abort_unless($membership, 404);
        $membership->update(['is_active' => ! $membership->is_active]);

        return back()->with('success', $membership->is_active ? 'Accès parent activé pour cet établissement.' : 'Accès parent désactivé pour cet établissement.');
    }

    public function toggleChildVisibility(SchoolParent $parent, Student $student): RedirectResponse
    {
        $link = $parent->students()->whereKey($student->id)->firstOrFail();
        $parent->students()->updateExistingPivot($student->id, ['is_visible' => ! $link->pivot->is_visible]);

        return back()->with('success', $link->pivot->is_visible ? 'Les données de l’enfant sont maintenant masquées au parent.' : 'Les données de l’enfant sont maintenant visibles au parent.');
    }

    private function syncChildren(SchoolParent $parent, array $studentIds): void
    {
        $existing = $parent->students()->get()->keyBy('id');
        $tenantId = app(TenantContext::class)->id();
        $payload = collect($studentIds)->mapWithKeys(fn ($id) => [(int) $id => [
            'tenant_id' => $tenantId,
            'is_primary' => (bool) ($existing->get((int) $id)?->pivot->is_primary ?? false),
            'is_visible' => (bool) ($existing->get((int) $id)?->pivot->is_visible ?? true),
        ]])->all();
        $parent->students()->sync($payload);
    }

    private function parentNames(User $user): array
    {
        if ($user->first_name || $user->last_name) {
            return [$user->first_name ?: $user->name, $user->last_name ?: ''];
        }
        $parts = preg_split('/\s+/', trim($user->name), 2);

        return [$parts[0] ?? $user->name, $parts[1] ?? ''];
    }
}
