<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\TimetableSession;
use App\Models\TrainingSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rooms and teachers are shared by the school timetable (weekly slots) and
 * formation sessions (dated slots) of the same tenant. These checks keep the
 * two calendars from double-booking each other.
 */
final class SharedResourceBookings
{
    /** School timetable slots that occupy the room or teacher during a dated formation session. */
    public function timetableConflicts(Carbon $starts, Carbon $ends, ?int $classroomId, ?int $teacherId): Collection
    {
        $date = $starts->toDateString();
        $from = $starts->format('H:i:s');
        $to = $ends->isSameDay($starts) ? $ends->format('H:i:s') : '23:59:59';

        return TimetableSession::with(['group:id,name', 'academicYear:id,start_date,end_date'])
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $to)->where('end_time', '>', $from)
            ->where(fn ($q) => $q->when($classroomId, fn ($q) => $q->orWhere('classroom_id', $classroomId))->when($teacherId, fn ($q) => $q->orWhere('teacher_id', $teacherId)))
            ->get()
            ->filter(function (TimetableSession $slot) use ($date, $starts) {
                if ($slot->effective_date) {
                    return $slot->effective_date->toDateString() === $date;
                }
                $year = $slot->academicYear;

                return $slot->day === $starts->dayOfWeekIso && $year && $starts->betweenIncluded($year->start_date->startOfDay(), $year->end_date->endOfDay())
                    && ! TimetableSession::where('parent_session_id', $slot->id)->whereDate('effective_date', $date)->exists();
            })->values();
    }

    /** Formation sessions that occupy the room or teacher during a weekly school slot of the given year. */
    public function trainingConflicts(array $slot, AcademicYear $year): Collection
    {
        return TrainingSession::where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('classroom_id', $slot['classroom_id'])->orWhere('teacher_id', $slot['teacher_id']))
            ->when($slot['effective_date'] ?? null,
                fn ($q, $date) => $q->whereDate('starts_at', $date),
                fn ($q) => $q->whereBetween('starts_at', [$year->start_date->copy()->startOfDay(), $year->end_date->copy()->endOfDay()]))
            ->get()
            ->filter(fn (TrainingSession $session) => $session->starts_at->dayOfWeekIso === (int) $slot['day']
                && $session->starts_at->format('H:i:s') < $this->time($slot['end_time'])
                && $session->ends_at->format('H:i:s') > $this->time($slot['start_time']))
            ->values();
    }

    private function time(string $value): string
    {
        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
