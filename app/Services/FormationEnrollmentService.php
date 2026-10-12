<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules for placing a student in a formation. A registration session caps
 * the number of registered students; a training plan group caps its own size.
 * Students are never matched automatically: the administrator picks an existing
 * student or creates a new one.
 */
final class FormationEnrollmentService
{
    /** Create a student file from the identity fields of an application or a form. */
    public function createStudent(array $data): Student
    {
        return Student::create([
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'email' => isset($data['email']) ? strtolower($data['email']) : null, 'phone' => $data['phone'] ?? null,
            'parent_phone' => $data['parent_phone'] ?? null, 'birth_date' => $data['birth_date'] ?? null,
            'school_level' => $data['level'] ?? null, 'registration_date' => now()->toDateString(),
            'status' => StudentStatus::ACTIVE, 'is_active' => true,
        ]);
    }

    /** Register an enrollment (new or existing application) for a student, optionally placing them in a group. */
    public function register(CourseEnrollment $enrollment, Student $student, ?TrainingPlanGroup $group, ?int $userId, string $description): CourseEnrollment
    {
        return DB::transaction(function () use ($enrollment, $student, $group, $userId, $description) {
            if ($enrollment->enrollment_form_id) {
                $form = EnrollmentForm::lockForUpdate()->findOrFail($enrollment->enrollment_form_id);
                if ($form->registeredCount() >= $form->max_students) {
                    throw ValidationException::withMessages(['student_id' => 'Le nombre maximum d’étudiants de cette session est atteint.']);
                }
            }
            $this->ensureNotAlreadyRegistered($student, $enrollment, $group);
            if ($group) {
                $this->ensureGroupAccepts($group, $enrollment->enrollment_form_id);
            }
            $from = $enrollment->exists ? $enrollment->status : null;
            $enrollment->fill([
                'student_id' => $student->id, 'training_plan_group_id' => $group?->id,
                'first_name' => $student->first_name, 'last_name' => $student->last_name,
                'email' => $enrollment->email ?: ($student->email ?: "student-{$student->id}@internal.invalid"),
                'phone' => $enrollment->phone ?: ($student->phone ?: '-'), 'parent_phone' => $enrollment->parent_phone ?? $student->parent_phone,
                'birth_date' => $enrollment->birth_date ?? $student->birth_date,
                'confirmation_token' => $enrollment->confirmation_token ?: (string) str()->uuid(),
                'confirmed_at' => $enrollment->confirmed_at ?? now(), 'approved_at' => $enrollment->approved_at ?? now(),
                'status' => ApplicationStatus::REGISTERED, 'registered_at' => now(), 'cancelled_at' => null,
            ])->save();
            $enrollment->histories()->create(['user_id' => $userId, 'event' => 'registered', 'from_status' => $from?->value, 'to_status' => 'registered', 'description' => $description, 'metadata' => ['training_plan_group_id' => $group?->id]]);

            return $enrollment;
        });
    }

    /** Move a registered enrollment to another group of the same session or plan, or leave it unplaced. */
    public function place(CourseEnrollment $enrollment, ?TrainingPlanGroup $group, ?int $userId): void
    {
        abort_unless($enrollment->status === ApplicationStatus::REGISTERED, 422, 'Seul un étudiant inscrit peut être affecté à un groupe.');
        if ($group?->id === $enrollment->training_plan_group_id) {
            return;
        }
        DB::transaction(function () use ($enrollment, $group, $userId) {
            if ($group) {
                $this->ensureGroupAccepts(TrainingPlanGroup::lockForUpdate()->findOrFail($group->id), $enrollment->enrollment_form_id);
            }
            $from = $enrollment->training_plan_group_id;
            $enrollment->update(['training_plan_group_id' => $group?->id]);
            $enrollment->histories()->create(['user_id' => $userId, 'event' => 'group_changed', 'description' => $group ? 'Affecté au groupe '.$group->name.'.' : 'Retiré de son groupe.', 'metadata' => ['from_group_id' => $from, 'to_group_id' => $group?->id]]);
        });
    }

    public function transition(CourseEnrollment $enrollment, ApplicationStatus $target, ?int $userId, ?string $description): void
    {
        $from = $enrollment->status;
        $allowed = match (true) {
            $target === ApplicationStatus::CANCELLED => ! in_array($from, [ApplicationStatus::CANCELLED, ApplicationStatus::COMPLETED], true),
            in_array($target, [ApplicationStatus::REGISTERED, ApplicationStatus::COMPLETED], true) => false,
            default => ! in_array($from, [ApplicationStatus::REGISTERED, ApplicationStatus::COMPLETED], true),
        };
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => $from === ApplicationStatus::REGISTERED
                ? 'Un étudiant inscrit ne peut qu’être annulé.'
                : 'Ce changement de statut n’est pas autorisé.']);
        }
        $timestamps = match ($target) {
            ApplicationStatus::CONTACTED => ['contacted_at' => now()], ApplicationStatus::APPROVED => ['approved_at' => now()],
            ApplicationStatus::REJECTED => ['rejected_at' => now()], ApplicationStatus::CANCELLED => ['cancelled_at' => now()], default => [],
        };
        $enrollment->update(['status' => $target, ...$timestamps]);
        $enrollment->histories()->create(['user_id' => $userId, 'event' => 'status_changed', 'from_status' => $from->value, 'to_status' => $target->value, 'description' => $description]);
    }

    private function ensureGroupAccepts(TrainingPlanGroup $group, ?int $sessionId): void
    {
        $group->loadMissing('plan');
        if ($sessionId && (int) $group->plan?->enrollment_form_id !== $sessionId) {
            throw ValidationException::withMessages(['training_plan_group_id' => 'Ce groupe n’appartient pas à une planification de cette session.']);
        }
        if (in_array($group->plan?->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['training_plan_group_id' => 'Cette planification est clôturée.']);
        }
        if (! $group->hasRoomFor()) {
            throw ValidationException::withMessages(['training_plan_group_id' => 'Ce groupe a atteint sa capacité maximale.']);
        }
    }

    private function ensureNotAlreadyRegistered(Student $student, CourseEnrollment $enrollment, ?TrainingPlanGroup $group): void
    {
        $active = CourseEnrollment::where('student_id', $student->id)->where('status', ApplicationStatus::REGISTERED)
            ->when($enrollment->exists, fn ($q) => $q->whereKeyNot($enrollment->id))
            ->where(function ($q) use ($enrollment, $group) {
                $q->when($enrollment->enrollment_form_id, fn ($q, $id) => $q->orWhere('enrollment_form_id', $id))
                    ->when($group, fn ($q) => $q->orWhereHas('trainingPlanGroup', fn ($g) => $g->where('training_plan_id', $group->training_plan_id)));
            });
        if (($enrollment->enrollment_form_id || $group) && $active->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Cet étudiant est déjà inscrit à cette session ou planification.']);
        }
    }
}
