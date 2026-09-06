<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RegistrationRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'active', 'rejected'])],
        ]);

        return Inertia::render('SuperAdmin/Registrations/Index', [
            'registrations' => Tenant::query()
                ->with(['subscriptionPlan:id,name,price,currency,billing_period'])
                ->whereNotNull('registration_submitted_at')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('registration_submitted_at')
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'counts' => Tenant::query()
                ->whereNotNull('registration_submitted_at')
                ->selectRaw('status, count(*) total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function approve(Tenant $tenant): RedirectResponse
    {
        abort_unless(in_array($tenant->status, ['pending', 'rejected'], true), 422);
        $expiresAt = match ($tenant->subscriptionPlan?->billing_period) {
            'monthly' => now()->addMonth(),
            'yearly' => now()->addYear(),
            default => null,
        };

        $tenant->update([
            'status' => 'active',
            'plan_started_at' => now(),
            'plan_expires_at' => $expiresAt,
            'registration_reviewed_at' => now(),
            'registration_rejection_reason' => null,
        ]);

        return back()->with('success', 'Inscription approuvée et compte activé.');
    }

    public function reject(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->status === 'pending', 422);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $tenant->update([
            'status' => 'rejected',
            'registration_reviewed_at' => now(),
            'registration_rejection_reason' => $data['reason'],
        ]);

        return back()->with('success', 'Inscription refusée.');
    }

    public function proof(Tenant $tenant): StreamedResponse
    {
        abort_unless($tenant->payment_proof_path && Storage::disk('local')->exists($tenant->payment_proof_path), 404);

        return Storage::disk('local')->download($tenant->payment_proof_path);
    }
}
