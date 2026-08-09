<?php

namespace App\Http\Controllers;

use App\Mail\EnrollmentConfirmationMail;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $confirmed = $enrollmentForm->enrollments()->whereNotNull('confirmed_at')->count();

        return Inertia::render('Public/EnrollmentForm', [
            'enrollmentForm' => $enrollmentForm,
            'confirmedCount' => $confirmed,
            'isAvailable' => $enrollmentForm->is_active && $confirmed < $enrollmentForm->max_students,
        ]);
    }

    public function store(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $confirmed = $enrollmentForm->enrollments()->whereNotNull('confirmed_at')->count();
        abort_unless($enrollmentForm->is_active && $confirmed < $enrollmentForm->max_students, 422, 'Les inscriptions sont fermées.');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        $existing = $enrollmentForm->enrollments()->where('email', $validated['email'])->first();
        if ($existing?->confirmed_at) {
            return back()->withErrors(['email' => 'Cette adresse e-mail est déjà inscrite à cette formation.']);
        }

        $enrollment = $enrollmentForm->enrollments()->updateOrCreate(
            ['email' => $validated['email']],
            [...$validated, 'confirmation_token' => (string) Str::uuid()],
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
            DB::transaction(function () use ($enrollment) {
                $form = EnrollmentForm::query()->lockForUpdate()->findOrFail($enrollment->enrollment_form_id);
                $confirmedCount = $form->enrollments()->whereNotNull('confirmed_at')->count();
                abort_if(! $form->is_active || $confirmedCount >= $form->max_students, 422, 'Cette formation est complète ou les inscriptions sont fermées.');

                $student = Student::query()->whereRaw('LOWER(email) = ?', [Str::lower($enrollment->email)])->first();
                if (! $student) {
                    $student = Student::create([
                        'first_name' => $enrollment->first_name,
                        'last_name' => $enrollment->last_name,
                        'email' => Str::lower($enrollment->email),
                        'phone' => $enrollment->phone,
                        'birth_date' => $enrollment->birth_date,
                        'is_active' => true,
                    ]);
                }

                $enrollment->update([
                    'student_id' => $student->id,
                    'confirmed_at' => now(),
                    'group_number' => ($confirmedCount % $form->groups_count) + 1,
                ]);
            });
        }

        $enrollment->load('form.course');

        return Inertia::render('Public/EnrollmentConfirmed', ['enrollment' => $enrollment]);
    }
}
