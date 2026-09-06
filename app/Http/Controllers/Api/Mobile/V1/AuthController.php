<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MobileMembership;
use App\Models\SchoolParent;
use App\Models\User;
use App\Services\Mobile\ParentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private ParentAccountService $accounts) {}

    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => str($request->input('email'))->lower()->toString()]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $email = str($data['email'])->lower()->toString();
        $user = User::create([
            'tenant_id' => null,
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::PARENT,
            'is_active' => true,
            'can_login' => true,
        ]);
        $token = $user->createToken('mobile:'.$data['device_name'], ['mobile', 'context:select'], now()->addDays(90));

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => [...$this->user($user), 'role' => 'parent'],
            'school' => null,
            'contexts' => [],
            'context_required' => true,
            'password_change_required' => false,
        ]], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['required', 'string', 'max:100']]);
        $user = User::withoutGlobalScopes()->where('email', str($data['email'])->lower())->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.', 'errors' => ['email' => ['Les identifiants fournis sont incorrects.']]], 422);
        }
        if (! $user->is_active || ! $user->can_login) {
            return response()->json(['message' => 'Votre compte est désactivé.', 'error' => 'account_inactive'], 403);
        }
        if ($user->role !== UserRole::PARENT) {
            return response()->json(['message' => 'Ce compte n’est pas autorisé sur l’application mobile.', 'error' => 'role_not_allowed'], 403);
        }
        if ($user->temporary_password_expires_at?->isPast()) {
            return response()->json(['message' => 'Le mot de passe temporaire a expiré. Demandez-en un nouveau.', 'error' => 'temporary_password_expired'], 403);
        }
        $memberships = $this->memberships($user);
        $token = $user->createToken('mobile:'.$data['device_name'], ['mobile', 'context:select'], now()->addDays(90));
        $firstContext = $memberships->first();

        return response()->json(['data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'user' => [...$this->user($user), 'role' => 'parent'], 'school' => $firstContext ? $this->context($firstContext)['school'] : null, 'contexts' => $memberships->map(fn (MobileMembership $membership) => $this->context($membership))->values(), 'context_required' => true, 'password_change_required' => $user->temporary_password_expires_at !== null]]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->merge(['email' => str($request->input('email'))->lower()->toString()]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = User::withoutGlobalScopes()->where('email', str($data['email'])->lower())->where('role', UserRole::PARENT->value)->first();
        if ($user && $user->is_active && $user->can_login) {
            $this->accounts->issueTemporaryPassword($user, 'password_reset');
        }

        return response()->json(['message' => 'Si un compte parent actif correspond à cette adresse, un mot de passe temporaire lui a été envoyé.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->mobileParent($request);
        $rules = ['password' => ['required', 'confirmed', Password::defaults()]];
        if (! $user->temporary_password_expires_at) {
            $rules['current_password'] = ['required', 'string'];
        }
        $data = $request->validate($rules);
        if (! $user->temporary_password_expires_at && ! Hash::check($data['current_password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'Le nouveau mot de passe doit être différent du mot de passe actuel.']);
        }
        $user->forceFill(['password' => Hash::make($data['password']), 'temporary_password_expires_at' => null])->save();
        $this->deleteOtherTokens($request, $user);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->user($this->mobileParent($request))]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->mobileParent($request);
        $request->merge(['email' => str($request->input('email'))->lower()->toString()]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'current_password' => ['nullable', 'string'],
        ]);
        $emailChanged = $data['email'] !== $user->email;
        if ($emailChanged && (! isset($data['current_password']) || ! Hash::check($data['current_password'], $user->password))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est requis pour modifier l’adresse e-mail.']);
        }

        $user->forceFill([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();
        SchoolParent::withoutGlobalScopes()->where('user_id', $user->id)->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
        ]);
        if ($emailChanged) {
            $this->deleteOtherTokens($request, $user);
        }

        return response()->json(['data' => $this->user($user->fresh()), 'meta' => ['email_changed' => $emailChanged]]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $this->mobileParent($request);
        $user->tokens()->delete();

        return response()->json(['message' => 'Tous les appareils ont été déconnectés.']);
    }

    public function contexts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->memberships($request->user())->map(fn (MobileMembership $membership) => $this->context($membership))->values()]);
    }

    public function selectContext(Request $request): JsonResponse
    {
        if ($request->user()->temporary_password_expires_at) {
            return response()->json(['message' => 'Choisissez un nouveau mot de passe avant de continuer.', 'error' => 'password_change_required'], 409);
        }
        $data = $request->validate(['tenant_id' => ['required', 'integer'], 'role' => ['nullable', 'in:parent'], 'device_name' => ['required', 'string', 'max:100']]);
        $membership = $this->memberships($request->user())->first(fn (MobileMembership $item) => $item->tenant_id === (int) $data['tenant_id']);
        if (! $membership) {
            return response()->json(['message' => 'Vous ne disposez pas d’un accès parent dans cet établissement.', 'error' => 'membership_not_found'], 403);
        }
        $token = $request->user()->createToken('mobile:'.$data['device_name'].':'.$membership->tenant->slug.':parent', ['mobile', 'membership:'.$membership->id], now()->addDays(90));

        return response()->json(['data' => ['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'context' => $this->context($membership)]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => [...$this->user($request->user()), 'active_context' => $this->context($request->attributes->get('mobile_membership'))]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function memberships(User $user)
    {
        $memberships = $user->mobileMemberships()->with('tenant')->where('role', UserRole::PARENT->value)->where('is_active', true)->get()->filter(fn (MobileMembership $membership) => $membership->tenant && $membership->tenant->status === 'active' && ! $membership->tenant->demoExpired());
        if ($memberships->isEmpty() && $user->tenant_id && $user->role === UserRole::PARENT) {
            $legacy = MobileMembership::firstOrCreate(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'role' => UserRole::PARENT->value], ['parent_id' => $user->schoolParent?->id, 'is_active' => true])->load('tenant');
            $memberships->push($legacy);
        }

        return $memberships;
    }

    private function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email, 'phone' => $user->phone];
    }

    private function mobileParent(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->role === UserRole::PARENT && $user->tokenCan('mobile'), 403, 'Accès réservé aux comptes parents.');

        return $user;
    }

    private function deleteOtherTokens(Request $request, User $user): void
    {
        $token = $request->user()->currentAccessToken();
        $query = $user->tokens();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken && $token->getKey()) {
            $query->whereKeyNot($token->getKey());
        }
        $query->delete();
    }

    private function context(MobileMembership $membership): array
    {
        return ['membership_id' => $membership->id, 'role' => 'parent', 'profile_id' => $membership->parent_id, 'school' => ['id' => $membership->tenant->id, 'name' => $membership->tenant->name, 'slug' => $membership->tenant->slug, 'logo_url' => $membership->tenant->logo_url]];
    }
}
