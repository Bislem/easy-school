<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientsController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Clients/Edit', ['client' => null]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateClient($request);
        $path = $request->file('driving_license_copy')->store('driving-licenses', 'public');
        $approved = $validated['approval_status'] === 'approved';

        $client = User::create([
            ...collect($validated)->except(['password', 'password_confirmation', 'driving_license_copy'])->toArray(),
            'password' => Hash::make($validated['password']),
            'driving_license_path' => $path,
            'role' => UserRole::CLIENT,
            'is_active' => $approved,
            'approved_at' => $approved ? now() : null,
            'approved_by' => $approved ? auth()->id() : null,
        ]);
        $client->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('admin.clients.show', $client)->with('success', 'Le compte client a été créé.');
    }

    public function edit(User $client): Response
    {
        abort_unless($client->role === UserRole::CLIENT, 404);
        return Inertia::render('Admin/Clients/Edit', [
            'client' => [
                'id' => $client->id, 'name' => $client->name, 'email' => $client->email,
                'phone' => $client->phone, 'birth_date' => $client->birth_date?->format('Y-m-d'),
                'driving_license_number' => $client->driving_license_number,
                'driving_license_delivered_at' => $client->driving_license_delivered_at?->format('Y-m-d'),
                'driving_license_authority' => $client->driving_license_authority,
                'driving_license_url' => $client->driving_license_url,
                'approval_status' => $client->approval_status,
                'rejection_reason' => $client->rejection_reason,
            ],
        ]);
    }

    public function update(Request $request, User $client)
    {
        abort_unless($client->role === UserRole::CLIENT, 404);
        $validated = $this->validateClient($request, $client);
        $data = collect($validated)->except(['password', 'password_confirmation', 'driving_license_copy'])->toArray();
        if (!empty($validated['password'])) $data['password'] = Hash::make($validated['password']);

        if ($request->hasFile('driving_license_copy')) {
            $newPath = $request->file('driving_license_copy')->store('driving-licenses', 'public');
            if ($client->driving_license_path) Storage::disk('public')->delete($client->driving_license_path);
            $data['driving_license_path'] = $newPath;
        }

        $approved = $validated['approval_status'] === 'approved';
        $data['is_active'] = $approved;
        $data['approved_at'] = $approved ? now() : null;
        $data['approved_by'] = $approved ? auth()->id() : null;
        if ($approved) $data['rejection_reason'] = null;
        $client->update($data);

        return redirect()->route('admin.clients.show', $client)->with('success', 'Les informations du client ont été mises à jour.');
    }

    private function validateClient(Request $request, ?User $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client?->id)],
            'phone' => ['required', 'string', 'max:50'],
            'password' => [$client ? 'nullable' : 'required', 'confirmed', 'min:8'],
            'birth_date' => ['required', 'date', 'before:today'],
            'driving_license_number' => ['required', 'string', 'max:100', Rule::unique('users', 'driving_license_number')->ignore($client?->id)],
            'driving_license_delivered_at' => ['required', 'date', 'before_or_equal:today', 'after:birth_date'],
            'driving_license_authority' => ['required', 'string', 'max:255'],
            'driving_license_copy' => [Rule::requiredIf(!$client?->driving_license_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'approval_status' => ['required', Rule::in(['approved', 'pending', 'rejected', 'suspended'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $query = User::query()
            ->where('role', UserRole::CLIENT)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($q) use ($status) {
                $q->where('approval_status', $status);
            })
            ->withCount(['reservations', 'payments'])
            ->orderBy('name');

        $clients = $query->paginate(10)->withQueryString();

        $statusCounts = [
            'approved' => User::where('role', UserRole::CLIENT)->where('approval_status', 'approved')->count(),
            'pending' => User::where('role', UserRole::CLIENT)->where('approval_status', 'pending')->count(),
            'rejected' => User::where('role', UserRole::CLIENT)->where('approval_status', 'rejected')->count(),
            'suspended' => User::where('role', UserRole::CLIENT)->where('approval_status', 'suspended')->count(),
        ];

        $statuses = [
            'approved' => ['label' => 'Approuvé', 'count' => $statusCounts['approved'], 'color' => '#10B981'],
            'pending' => ['label' => 'En attente', 'count' => $statusCounts['pending'], 'color' => '#F59E0B'],
            'rejected' => ['label' => 'Refusé', 'count' => $statusCounts['rejected'], 'color' => '#DC2626'],
            'suspended' => ['label' => 'Suspendu', 'count' => $statusCounts['suspended'], 'color' => '#EF4444'],
        ];

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => $statuses,
        ]);
    }

    public function show(User $client): Response
    {
        $totalSpent = Payment::where('user_id', $client->id)
            ->where('status', PaymentStatus::COMPLETED)
            ->sum('amount');

        $reservations = $client->reservations()
            ->with(['car'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'reservations_page')
            ->withQueryString();

        $payments = $client->payments()
            ->with(['reservation'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'payments_page')
            ->withQueryString();

        return Inertia::render('Admin/Clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'is_active' => (bool) $client->is_active,
                'approval_status' => $client->approval_status,
                'birth_date' => $client->birth_date,
                'driving_license_number' => $client->driving_license_number,
                'driving_license_delivered_at' => $client->driving_license_delivered_at,
                'driving_license_authority' => $client->driving_license_authority,
                'driving_license_url' => $client->driving_license_url,
                'rejection_reason' => $client->rejection_reason,
                'created_at' => $client->created_at,
            ],
            'stats' => [
                'total_reservations' => $client->reservations()->count(),
                'total_payments' => $client->payments()->count(),
                'total_spent' => (float) $totalSpent,
            ],
            'reservations' => $reservations,
            'payments' => $payments,
            'drivers' => $client->drivers()->latest()->get(),
        ]);
    }

    public function suspend(User $client)
    {
        // Restrict this action
        // return redirect()
        //     ->back()
        //     ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');

        $client->is_active = false;
        $client->approval_status = 'suspended';
        $client->save();

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Le client a été suspendu avec succès.');
    }

    public function activate(User $client)
    {
        // Restrict this action
        // return redirect()
        //     ->back()
        //     ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');

        $client->is_active = true;
        $client->approval_status = 'approved';
        $client->approved_at = now();
        $client->approved_by = auth()->id();
        $client->rejection_reason = null;
        $client->save();

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Le client a été activé avec succès.');
    }

    public function reject(Request $request, User $client)
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $client->update([
            'is_active' => false,
            'approval_status' => 'rejected',
            'rejection_reason' => $validated['reason'] ?? null,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return back()->with('success', 'La demande du client a été refusée.');
    }

    public function approveDriver(User $client, Driver $driver)
    {
        abort_unless($driver->user_id === $client->id, 404);

        DB::transaction(function () use ($driver) {
            $driver->update([
                'approval_status' => 'approved', 'rejection_reason' => null,
                'approved_at' => now(), 'approved_by' => auth()->id(),
            ]);

            $driver->requestedReservations()->update([
                'secondary_driver_id' => $driver->id,
                'requested_driver_id' => null,
            ]);
        });

        return back()->with('success', 'Le conducteur a été approuvé et affecté aux réservations qui le demandaient.');
    }

    public function rejectDriver(Request $request, User $client, Driver $driver)
    {
        abort_unless($driver->user_id === $client->id, 404);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($driver, $validated) {
            $driver->update([
                'approval_status' => 'rejected',
                'rejection_reason' => $validated['reason'] ?? null,
                'approved_at' => null, 'approved_by' => null,
            ]);
            $driver->requestedReservations()->update(['requested_driver_id' => null]);
        });

        return back()->with('success', 'Le conducteur a été refusé.');
    }
}
