<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceException;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendanceService
{
    public const PRESENT = 'PRESENT';

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
