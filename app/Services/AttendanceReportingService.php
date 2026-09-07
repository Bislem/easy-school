<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Models\AttendanceException;
use App\Models\AttendanceSetting;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceReportingService
{
    private array $sessionCache = [];

    public function __construct(private AttendanceService $attendance) {}

    public function student(Student $student, AcademicYear $year, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        [$start, $end] = $this->range($year, $from, $to);
        $enrollment = StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('student_id', $student->id)->first();
        $totals = $this->emptyStudent();
        if (! $enrollment?->school_group_id || $end->lt($start)) {
            return $totals;
        }
        $start = $start->max(Carbon::parse($enrollment->enrollment_date));
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            foreach ($this->sessionsOn($year, $day, 'students', groupId: $enrollment->school_group_id) as $session) {
                $exception = $this->attendance->getStudentException($student, $session, $day);
                $status = $exception?->status->value ?? AttendanceService::PRESENT;
                $totals['scheduled_sessions']++;
                if (in_array($status, ['ABSENT', 'EXCUSED'], true)) {
                    $totals['absences']++;
                    $totals[$this->justified($exception) ? 'justified_absences' : 'unjustified_absences']++;
                    $totals['absence_dates'][] = $day->toDateString();
                } elseif ($status === 'LATE') {
                    $totals['late_arrivals']++;
                }
            }
        }
        $totals['present_sessions'] = $totals['scheduled_sessions'] - $totals['absences'];
        $totals['attendance_percentage'] = $this->percentage($totals['present_sessions'], $totals['scheduled_sessions']);
        $totals['absence_dates'] = array_values(array_unique($totals['absence_dates']));

        return $totals;
    }

    public function teacher(User $teacher, AcademicYear $year, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        [$start, $end] = $this->range($year, $from, $to);
        $totals = $this->emptyTeacher();
        if ($end->lt($start)) {
            return $totals;
        }
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            foreach ($this->sessionsOn($year, $day, 'teachers', teacherId: $teacher->id) as $session) {
                $exception = $this->attendance->getTeacherException($teacher, $session, $day);
                $status = $exception?->status->value ?? AttendanceService::PRESENT;
                $totals['scheduled_sessions']++;
                if (in_array($status, ['ABSENT', 'EXCUSED'], true)) {
                    $totals['absences']++;
                    $totals[$this->justified($exception) ? 'justified_absences' : 'unjustified_absences']++;
                    $totals['affected_teaching_sessions'][] = $this->impactedSession($session, $day);
                } elseif ($status === 'LATE') {
                    $totals['late_arrivals']++;
                }
            }
        }
        $totals['present_sessions'] = $totals['scheduled_sessions'] - $totals['absences'];
        $totals['attendance_percentage'] = $this->percentage($totals['present_sessions'], $totals['scheduled_sessions']);

        return $totals;
    }

    public function studentHistory(Student $student, AcademicYear $year): array
    {
        $history = [];
        for ($month = $year->start_date->copy()->startOfMonth(); $month->lte($year->end_date); $month->addMonth()) {
            $stats = $this->student($student, $year, $month->copy()->max($year->start_date), $month->copy()->endOfMonth()->min($year->end_date));
            $history[] = ['period' => $month->format('Y-m'), ...collect($stats)->except('absence_dates')->all()];
        }

        return $history;
    }

    public function todayDashboard(AcademicYear $year, CarbonInterface|string $date): array
    {
        $day = Carbon::parse($date);
        $studentIds = [];
        $lateIds = [];
        foreach (StudentAcademicEnrollment::with('student')->where('academic_year_id', $year->id)->where('status', 'enrolled')->get() as $enrollment) {
            if (! $enrollment->student || ! $enrollment->school_group_id) {
                continue;
            }
            foreach ($this->sessionsOn($year, $day, 'students', groupId: $enrollment->school_group_id) as $session) {
                $status = $this->attendance->getStudentStatus($enrollment->student, $session, $day);
                if (in_array($status, ['ABSENT', 'EXCUSED'], true)) {
                    $studentIds[$enrollment->student_id] = true;
                } elseif ($status === 'LATE') {
                    $lateIds[$enrollment->student_id] = true;
                }
            }
        }
        $teacherIds = [];
        $classes = [];
        foreach ($this->sessionsOn($year, $day, 'teachers') as $session) {
            if ($session->teacher && in_array($this->attendance->getTeacherStatus($session->teacher, $session, $day), ['ABSENT', 'EXCUSED'], true)) {
                $teacherIds[$session->teacher_id] = true;
                $classes[$session->school_group_id] = true;
            }
        }

        return ['students_absent' => count($studentIds), 'students_late' => count($lateIds), 'teachers_absent' => count($teacherIds), 'affected_classes' => count($classes)];
    }

    public function warnings(AcademicYear $year, CarbonInterface|string $date): array
    {
        $settings = AttendanceSetting::current();
        $month = Carbon::parse($date);
        $warnings = [];
        $candidateIds = AttendanceException::where('academic_year_id', $year->id)->where('person_type', 'STUDENT')
            ->whereIn('status', ['ABSENT', 'EXCUSED'])->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->distinct()->pluck('student_id');
        foreach (StudentAcademicEnrollment::with('student')->where('academic_year_id', $year->id)->where('status', 'enrolled')->whereIn('student_id', $candidateIds)->get() as $enrollment) {
            if (! $enrollment->student) {
                continue;
            }
            $stats = $this->student($enrollment->student, $year, $month->copy()->startOfMonth(), $month->copy()->endOfMonth());
            $consecutive = $this->longestConsecutiveDays($stats['absence_dates']);
            if ($stats['absences'] >= $settings->monthly_absence_threshold || $consecutive >= $settings->consecutive_days_threshold) {
                $warnings[] = ['student_id' => $enrollment->student_id, 'student' => $enrollment->student->full_name,
                    'monthly_absences' => $stats['absences'], 'consecutive_days' => $consecutive];
            }
        }

        return $warnings;
    }

    public function sessionsOn(AcademicYear $year, CarbonInterface|string $date, string $audience, ?int $groupId = null, ?int $teacherId = null): Collection
    {
        $day = Carbon::parse($date);
        $dateString = $day->toDateString();
        $cacheKey = implode(':', [$year->id, $dateString, $audience, $groupId ?: 0, $teacherId ?: 0]);
        if (isset($this->sessionCache[$cacheKey])) {
            return $this->sessionCache[$cacheKey];
        }
        if (AcademicYearCalendarEvent::where('academic_year_id', $year->id)->whereDate('starts_on', '<=', $dateString)->whereDate('ends_on', '>=', $dateString)->whereIn('applies_to', ['both', $audience])->exists()) {
            return $this->sessionCache[$cacheKey] = collect();
        }
        $replaced = TimetableSession::where('academic_year_id', $year->id)->whereDate('effective_date', $dateString)->whereNotNull('parent_session_id')->pluck('parent_session_id');

        return $this->sessionCache[$cacheKey] = TimetableSession::with(['subject:id,title,title_ar', 'teacher:id,name', 'group:id,name,school_level_id'])
            ->where('academic_year_id', $year->id)->where('status', '!=', 'cancelled')
            ->when($groupId, fn ($q) => $q->where('school_group_id', $groupId))->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->where(fn ($q) => $q->where(fn ($weekly) => $weekly->where('recurrence', 'weekly')->where('day', $day->dayOfWeekIso)->whereNull('parent_session_id')->whereNotIn('id', $replaced))
                ->orWhere(fn ($once) => $once->where('recurrence', 'once')->whereDate('effective_date', $dateString)))->orderBy('start_time')->get();
    }

    private function impactedSession(TimetableSession $session, CarbonInterface $date): array
    {
        return ['date' => $date->toDateString(), 'session_id' => $session->id, 'group' => $session->group?->name,
            'subject' => $session->subject?->title, 'start_time' => substr($session->start_time, 0, 5), 'end_time' => substr($session->end_time, 0, 5),
            'substitution' => ['status' => 'UNASSIGNED', 'can_assign' => false]];
    }

    private function range(AcademicYear $year, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $start = Carbon::parse($from)->max($year->start_date);
        $end = Carbon::parse($to)->min($year->end_date)->min(today());

        return [$start, $end];
    }

    private function justified(?AttendanceException $exception): bool
    {
        return $exception && ($exception->status->value === 'EXCUSED' || $exception->justified_at || filled($exception->justification));
    }

    private function percentage(int $present, int $scheduled): float
    {
        return $scheduled ? round($present * 100 / $scheduled, 1) : 100.0;
    }

    private function emptyStudent(): array
    {
        return ['scheduled_sessions' => 0, 'present_sessions' => 0, 'absences' => 0, 'justified_absences' => 0, 'unjustified_absences' => 0, 'late_arrivals' => 0, 'attendance_percentage' => 100.0, 'absence_dates' => []];
    }

    private function emptyTeacher(): array
    {
        return ['scheduled_sessions' => 0, 'present_sessions' => 0, 'absences' => 0, 'justified_absences' => 0, 'unjustified_absences' => 0, 'late_arrivals' => 0, 'attendance_percentage' => 100.0, 'affected_teaching_sessions' => []];
    }

    private function longestConsecutiveDays(array $dates): int
    {
        sort($dates);
        $longest = $current = 0;
        $previous = null;
        foreach ($dates as $date) {
            $day = Carbon::parse($date);
            $current = $previous && $previous->diffInDays($day) === 1 ? $current + 1 : 1;
            $longest = max($longest, $current);
            $previous = $day;
        }

        return $longest;
    }
}
