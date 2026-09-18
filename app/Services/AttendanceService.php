<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceException;
use App\Models\CourseEnrollment;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TeacherAttendance;
use App\Models\TimetableSession;
use App\Models\TrainingPlanGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public const PRESENT = 'PRESENT';

    /**
     * Synchronize the exceptional student absences for one training session.
     * Students without a row are present implicitly.
     *
     * @param  array<int>  $absentStudentIds
     */
    public function syncTrainingSessionAbsences(TrainingSession $session, array $absentStudentIds, int $userId): void
    {
        $absentStudentIds = collect($absentStudentIds)->map(fn ($id) => (int) $id)->unique()->values();
        $enrollments = CourseEnrollment::where('training_plan_group_id', $session->training_plan_group_id)
            ->where('status', 'registered')->whereNotNull('student_id')->get()->keyBy('student_id');
        if ($absentStudentIds->diff($enrollments->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['student_ids' => "Un étudiant sélectionné n'appartient pas à ce groupe."]);
        }

        DB::transaction(function () use ($session, $absentStudentIds, $enrollments, $userId): void {
            SessionAttendance::where('training_session_id', $session->id)
                ->whereNotIn('student_id', $absentStudentIds)->delete();

            foreach ($absentStudentIds as $studentId) {
                SessionAttendance::updateOrCreate(
                    ['training_session_id' => $session->id, 'student_id' => $studentId],
                    [
                        'course_enrollment_id' => $enrollments->get($studentId)?->id,
                        'status' => 'absent',
                        'recorded_at' => now(),
                        'recorded_by' => $userId,
                    ],
                );
            }

            if ($session->attendance_status !== 'validated') {
                $session->update(['attendance_status' => 'completed']);
            }
        });
    }

    /**
     * Synchronize one student's absence exceptions for all editable sessions on a day.
     * Existing absences belonging to other students are never touched.
     *
     * @param  array<int>  $absentSessionIds
     */
    public function syncStudentDayAbsences(TrainingPlanGroup $group, Student $student, string $date, array $absentSessionIds, int $userId): void
    {
        $enrollment = CourseEnrollment::where('training_plan_group_id', $group->id)
            ->where('student_id', $student->id)->where('status', 'registered')->first();
        if (! $enrollment) {
            throw ValidationException::withMessages(['student_id' => "Cet étudiant n'appartient pas au groupe sélectionné."]);
        }

        $sessions = TrainingSession::where('training_plan_group_id', $group->id)
            ->whereDate('starts_at', Carbon::parse($date)->toDateString())
            ->where('status', '!=', 'cancelled')->get();
        $editable = $sessions->filter(fn (TrainingSession $session) => ! $session->attendance_locked_at && $session->attendance_status !== 'validated');
        $selected = collect($absentSessionIds)->map(fn ($id) => (int) $id)->unique()->values();

        if ($selected->diff($editable->modelKeys())->isNotEmpty()) {
            throw ValidationException::withMessages(['session_ids' => 'Une séance sélectionnée est invalide ou déjà verrouillée.']);
        }

        DB::transaction(function () use ($editable, $selected, $student, $enrollment, $userId): void {
            SessionAttendance::where('student_id', $student->id)
                ->whereIn('training_session_id', $editable->modelKeys())
                ->whereNotIn('training_session_id', $selected)->delete();

            foreach ($selected as $sessionId) {
                SessionAttendance::updateOrCreate(
                    ['training_session_id' => $sessionId, 'student_id' => $student->id],
                    ['course_enrollment_id' => $enrollment->id, 'status' => 'absent', 'recorded_at' => now(), 'recorded_by' => $userId],
                );
            }

            TrainingSession::whereIn('id', $editable->modelKeys())
                ->where('attendance_status', 'pending')->update(['attendance_status' => 'completed']);
        });
    }

    public function validate(TrainingSession $session, int $userId): void
    {
        DB::transaction(function () use ($session, $userId): void {
            $workedMinutes = $session->starts_at && $session->ends_at
                ? $session->starts_at->diffInMinutes($session->ends_at)
                : 0;

            TeacherAttendance::updateOrCreate(
                ['training_session_id' => $session->id],
                [
                    'scheduled_teacher_id' => $session->teacher_id,
                    'actual_teacher_id' => $session->teacher_id,
                    'status' => 'present',
                    'worked_minutes' => $workedMinutes,
                    'is_justified' => false,
                    'recorded_by' => $userId,
                    'validated_at' => now(),
                    'validated_by' => $userId,
                ],
            );

            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
                'attendance_status' => 'validated',
                'attendance_locked_at' => now(),
                'attendance_locked_by' => $userId,
            ]);
        });
    }

    /**
     * Compatibility entry point for teacher sheets and individual corrections.
     * Only absence exceptions are persisted; every other status removes the row.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function recordStudents(TrainingSession $session, array $records, int $userId, ?string $reason = null, bool $correction = false): void
    {
        if ($session->attendance_locked_at || $session->attendance_status === 'validated') {
            throw ValidationException::withMessages(['attendance' => 'Les absences de cette séance sont verrouillées.']);
        }

        if (! $correction) {
            $absentIds = collect($records)
                ->filter(fn (array $record) => in_array($record['status'] ?? null, ['absent', 'excused'], true))
                ->pluck('student_id')->all();
            $this->syncTrainingSessionAbsences($session, $absentIds, $userId);

            return;
        }

        $enrollments = CourseEnrollment::where('training_plan_group_id', $session->training_plan_group_id)
            ->where('status', 'registered')->whereNotNull('student_id')->get()->keyBy('student_id');

        DB::transaction(function () use ($session, $records, $enrollments, $userId): void {
            foreach ($records as $record) {
                $studentId = (int) $record['student_id'];
                if (! $enrollments->has($studentId)) {
                    throw ValidationException::withMessages(['records' => "Un étudiant sélectionné n'appartient pas à ce groupe."]);
                }

                if (in_array($record['status'] ?? null, ['absent', 'excused'], true)) {
                    SessionAttendance::updateOrCreate(
                        ['training_session_id' => $session->id, 'student_id' => $studentId],
                        ['course_enrollment_id' => $enrollments->get($studentId)->id, 'status' => 'absent', 'recorded_at' => now(), 'recorded_by' => $userId],
                    );
                } else {
                    SessionAttendance::where('training_session_id', $session->id)->where('student_id', $studentId)->delete();
                }
            }

            $session->update(['attendance_status' => 'completed']);
        });
    }

    public function getStudentStatus(Student $student, TimetableSession|CarbonInterface|string $sessionOrDate, CarbonInterface|string|null $date = null): string
    {
        return $this->getStudentException($student, $sessionOrDate, $date)?->status->value ?? self::PRESENT;
    }

    public function getStudentException(Student $student, TimetableSession|CarbonInterface|string $sessionOrDate, CarbonInterface|string|null $date = null): ?AttendanceException
    {
        [$session, $targetDate] = $this->context($sessionOrDate, $date);
        $year = $this->academicYear($targetDate, $session, $student);
        if (! $year) {
            return null;
        }

        return AttendanceException::query()
            ->where('academic_year_id', $year->id)
            ->where('person_type', 'STUDENT')
            ->where('student_id', $student->id)
            ->whereDate('date', $targetDate)
            ->where(function ($query) use ($session) {
                $query->whereNull('timetable_session_id');
                if ($session) {
                    $query->orWhere('timetable_session_id', $session->id);
                }
            })
            ->orderByRaw('timetable_session_id is null asc')
            ->first();
    }

    public function getTeacherStatus(User $teacher, TimetableSession|CarbonInterface|string $sessionOrDate, CarbonInterface|string|null $date = null): string
    {
        return $this->getTeacherException($teacher, $sessionOrDate, $date)?->status->value ?? self::PRESENT;
    }

    public function getTeacherException(User $teacher, TimetableSession|CarbonInterface|string $sessionOrDate, CarbonInterface|string|null $date = null): ?AttendanceException
    {
        [$session, $targetDate] = $this->context($sessionOrDate, $date);
        $year = $this->academicYear($targetDate, $session);
        if (! $year) {
            return null;
        }

        return AttendanceException::query()
            ->where('academic_year_id', $year->id)
            ->where('person_type', 'TEACHER')
            ->where('teacher_id', $teacher->id)
            ->whereDate('date', '<=', $targetDate)
            ->where(fn ($query) => $query->whereNull('end_date')->whereDate('date', $targetDate)->orWhereDate('end_date', '>=', $targetDate))
            ->where(function ($query) use ($session) {
                $query->whereNull('timetable_session_id');
                if ($session) {
                    $query->orWhere('timetable_session_id', $session->id);
                }
            })
            ->orderByRaw('timetable_session_id is null asc')
            ->first();
    }

    private function context(TimetableSession|CarbonInterface|string $sessionOrDate, CarbonInterface|string|null $date): array
    {
        $session = $sessionOrDate instanceof TimetableSession ? $sessionOrDate : null;
        $value = $session ? ($date ?? $session->effective_date ?? now()) : $sessionOrDate;

        return [$session, Carbon::parse($value)->toDateString()];
    }

    private function academicYear(string $date, ?TimetableSession $session, ?Student $student = null): ?AcademicYear
    {
        if ($session) {
            return $session->academicYear;
        }

        $query = AcademicYear::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
        if ($student) {
            $yearId = StudentAcademicEnrollment::where('student_id', $student->id)
                ->whereHas('academicYear', fn ($year) => $year->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date))
                ->value('academic_year_id');

            return $yearId ? AcademicYear::find($yearId) : null;
        }

        return $query->first();
    }
}
