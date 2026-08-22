<?php

namespace App\Http\Controllers;

use App\Mail\EnrollmentConfirmationMail;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
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
        $enrollmentForm->load(['course', 'teacher:id,name', 'classroom:id,name,code', 'files']);
        $confirmed = $enrollmentForm->enrollments()->where('status', 'registered')->count();

        return Inertia::render('Public/EnrollmentForm', [
            'enrollmentForm' => $enrollmentForm,
            'confirmedCount' => $confirmed,
            'isAvailable' => $enrollmentForm->is_active && $confirmed < $enrollmentForm->max_students,
        ]);
    }

    public function store(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $confirmed = $enrollmentForm->enrollments()->where('status', 'registered')->count();
        abort_unless($enrollmentForm->is_active && $confirmed < $enrollmentForm->max_students, 422, 'Les inscriptions sont fermées.');

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

        $existing = $enrollmentForm->enrollments()->where('email', $validated['email'])->first();
        if ($existing?->confirmed_at) {
            return back()->withErrors(['email' => 'Une demande confirmée existe déjà pour cette adresse e-mail.']);
        }

        $enrollment = $enrollmentForm->enrollments()->updateOrCreate(
            ['email' => $validated['email']],
            [...$validated, 'status' => 'new', 'confirmation_token' => (string) Str::uuid()],
        );
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
}
