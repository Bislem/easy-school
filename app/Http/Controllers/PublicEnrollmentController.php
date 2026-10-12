<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Mail\EnrollmentConfirmationMail;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicEnrollmentController extends Controller
{
    public function show(EnrollmentForm $enrollmentForm): Response
    {
        app(TenantContext::class)->set($enrollmentForm->tenant_id);
        $enrollmentForm->load(['course', 'teacher:id,name', 'classroom:id,name,code', 'files']);

        return Inertia::render('Public/EnrollmentForm', [
            'enrollmentForm' => $enrollmentForm,
            'confirmedCount' => $enrollmentForm->registeredCount(),
            'isAvailable' => $this->open($enrollmentForm),
        ]);
    }

    public function store(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        app(TenantContext::class)->set($enrollmentForm->tenant_id);
        abort_unless($this->open($enrollmentForm), 422, 'Les inscriptions sont fermées.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'level' => ['nullable', 'string', 'max:100'],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        // One open application per applicant (same e-mail and name): siblings may share an e-mail.
        $existing = $enrollmentForm->enrollments()->where('email', $validated['email'])
            ->whereRaw('LOWER(first_name) = ? AND LOWER(last_name) = ?', [Str::lower($validated['first_name']), Str::lower($validated['last_name'])])
            ->latest('id')->first();
        $closed = [ApplicationStatus::REJECTED, ApplicationStatus::CANCELLED];
        if ($existing?->confirmed_at && ! in_array($existing->status, $closed, true)) {
            return back()->withErrors(['email' => 'Une demande confirmée existe déjà pour cet étudiant.']);
        }

        $attributes = [...$validated, 'status' => ApplicationStatus::NEW, 'confirmation_token' => (string) Str::uuid(), 'confirmed_at' => null];
        $enrollment = $existing && ! $existing->student_id ? tap($existing)->update($attributes) : $enrollmentForm->enrollments()->create($attributes);
        $enrollment->load('form.course');

        $confirmationUrl = URL::temporarySignedRoute(
            'public.enrollment.confirm',
            now()->addHours(48),
            ['enrollment' => $enrollment, 'token' => $enrollment->confirmation_token],
        );
        Mail::to($enrollment->email)->send(new EnrollmentConfirmationMail($enrollment, $confirmationUrl));

        return back()->with('enrollment_pending', true);
    }

    public function confirm(CourseEnrollment $enrollment, string $token): Response
    {
        app(TenantContext::class)->set($enrollment->tenant_id);
        if (! hash_equals($enrollment->confirmation_token, $token)) {
            abort(403);
        }

        if (! $enrollment->confirmed_at) {
            $enrollment->update(['confirmed_at' => now(), 'status' => 'waiting']);
            $enrollment->histories()->create([
                'event' => 'email_confirmed', 'from_status' => 'new', 'to_status' => 'waiting',
                'description' => 'Adresse e-mail confirmée par le candidat.',
            ]);
        }

        $enrollment->load('form.course');

        return Inertia::render('Public/EnrollmentConfirmed', ['enrollment' => $enrollment]);
    }

    private function open(EnrollmentForm $enrollmentForm): bool
    {
        return (bool) $enrollmentForm->tenant?->hasAccess() && $enrollmentForm->acceptsApplications();
    }
}
