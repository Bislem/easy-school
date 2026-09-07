<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\DemoAccountApprovedMail;
use App\Mail\DemoAccountRejectedMail;
use App\Models\DemoRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantInitializer;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DemoRequestsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'suspended'])]]);

        return Inertia::render('SuperAdmin/DemoRequests/Index', [
            'requests' => DemoRequest::with(['tenant:id,name,status,demo_expires_at', 'reviewer:id,name'])->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest()->paginate(15)->withQueryString(),
            'filters' => $filters, 'counts' => DemoRequest::selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, DemoRequest $demoRequest, TenantInitializer $initializer): RedirectResponse
    {
        abort_unless($demoRequest->status === 'pending', 422);
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:15']]);
        $password = $this->provision($demoRequest, $data['days'], $initializer);
        $this->sendCredentials($demoRequest, $password);

        return back()->with('success', 'Démonstration approuvée et identifiants envoyés.');
    }

    public function store(Request $request, TenantInitializer $initializer): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_type' => ['required', Rule::in(['private_school', 'training_center', 'language_school', 'other'])],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_role' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'wilaya' => ['required', 'string', 'max:100'],
            'commune' => ['required', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'students_count' => ['required', 'integer', 'min:0', 'max:100000'],
            'teachers_count' => ['required', 'integer', 'min:0', 'max:10000'],
            'staff_count' => ['required', 'integer', 'min:0', 'max:10000'],
            'sites_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'requested_days' => ['required', 'integer', 'min:1', 'max:15'],
            'needs' => ['nullable', 'string', 'max:3000'],
            'modules' => ['nullable', 'array', 'max:12'],
            'modules.*' => ['string', Rule::in(['students', 'hr', 'multi_sites', 'courses', 'planning', 'certificates', 'badges', 'mobile', 'finance', 'reports'])],
        ]);

        $demoRequest = DemoRequest::create($data + ['status' => 'pending']);
        try {
            $password = $this->provision($demoRequest, $data['requested_days'], $initializer);
        } catch (\Throwable $exception) {
            $demoRequest->delete();
            throw $exception;
        }
        $this->sendCredentials($demoRequest, $password);

        return back()->with('success', 'Compte de démonstration créé et identifiants envoyés.');
    }

    private function provision(DemoRequest $demoRequest, int $days, TenantInitializer $initializer): string
    {
        $password = Str::password(14);
        DB::transaction(function () use ($demoRequest, $initializer, $days, $password) {
            $tenant = Tenant::create(['name' => $demoRequest->school_name, 'slug' => $this->uniqueSlug($demoRequest->school_name), 'phone' => $demoRequest->phone, 'email' => Str::lower($demoRequest->email), 'address' => $demoRequest->address, 'wilaya' => $demoRequest->wilaya, 'commune' => $demoRequest->commune, 'status' => 'active', 'account_type' => 'demo', 'organization_type' => $demoRequest->school_type, 'demo_expires_at' => now()->addDays($days)->endOfDay()]);
            app(TenantContext::class)->set($tenant);
            try {
                $admin = User::create(['name' => $demoRequest->contact_name, 'email' => Str::lower($demoRequest->email), 'phone' => $demoRequest->phone, 'password' => $password, 'role' => UserRole::ADMIN, 'is_active' => true, 'can_login' => true]);
                $admin->forceFill(['email_verified_at' => now()])->save();
                $initializer->initialize($tenant);
            } finally {
                app(TenantContext::class)->clear();
            }
            $demoRequest->update(['status' => 'approved', 'reviewed_by' => Auth::guard('super_admin')->id(), 'reviewed_at' => now(), 'tenant_id' => $tenant->id]);
        });

        return $password;
    }

    private function sendCredentials(DemoRequest $demoRequest, string $password): void
    {
        Mail::to($demoRequest->email)->send(new DemoAccountApprovedMail($demoRequest->fresh('tenant'), $password));
        $demoRequest->update(['credentials_sent_at' => now()]);
    }

    public function reject(Request $request, DemoRequest $demoRequest): RedirectResponse
    {
        abort_unless($demoRequest->status === 'pending', 422);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $demoRequest->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'reviewed_by' => Auth::guard('super_admin')->id(), 'reviewed_at' => now()]);
        Mail::to($demoRequest->email)->send(new DemoAccountRejectedMail($demoRequest->fresh()));

        return back()->with('success', 'Demande refusée et motif envoyé au responsable.');
    }

    public function extend(DemoRequest $demoRequest): RedirectResponse
    {
        abort_unless($demoRequest->tenant?->isDemo(), 422);
        $base = $demoRequest->tenant->demo_expires_at?->isFuture()
            ? $demoRequest->tenant->demo_expires_at->copy()
            : now();
        $demoRequest->tenant->update(['demo_expires_at' => $base->addDays(15)->endOfDay(), 'status' => 'active']);
        $demoRequest->update(['status' => 'approved']);

        return back()->with('success', 'Démonstration prolongée de 15 jours.');
    }

    public function suspend(DemoRequest $demoRequest): RedirectResponse
    {
        abort_unless($demoRequest->tenant?->isDemo(), 422);
        $demoRequest->tenant->update(['status' => 'suspended']);
        $demoRequest->update(['status' => 'suspended']);

        return back()->with('success', 'Démonstration suspendue.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'school';
        $slug = $base.'-demo';
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-demo-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
