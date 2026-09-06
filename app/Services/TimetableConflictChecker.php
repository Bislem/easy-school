<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\TeacherAvailability;
use App\Models\TeacherUnavailablePeriod;
use App\Models\TimetableSession;
use App\Models\TimetableSetting;
use App\Models\TrainingPlanGroup;
use App\Support\TimetableConflictResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimetableConflictChecker
{
    public function check(array $data, ?TimetableSession $except = null, array $exceptIds = []): TimetableConflictResult
    {
        $group = TrainingPlanGroup::withCount(['students', 'enrollments'])->findOrFail($data['training_plan_group_id']);
        $room = Classroom::findOrFail($data['classroom_id']);
        $subject = Course::findOrFail($data['course_id']);
        $period = AcademicPeriod::findOrFail($data['academic_period_id']);
        $conflicts = [];
        $warnings = [];

        if ($data['start_time'] >= $data['end_time']) {
            $conflicts[] = ['type' => 'invalid_time', 'field' => 'end_time', 'message' => 'Session end time must be after its start time.'];
        }
        if (! empty($data['effective_date'])) {
            $date = Carbon::parse($data['effective_date']);
            if (! $date->betweenIncluded($period->starts_on, $period->ends_on)) $conflicts[] = ['type' => 'invalid_date', 'field' => 'effective_date', 'message' => 'The exceptional date must be inside the academic period.'];
            if ($date->dayOfWeekIso !== (int) $data['day']) $data['day'] = $date->dayOfWeekIso;
        }

        if (! $room->is_active || ! $room->is_available) {
            $conflicts[] = ['type' => 'room_unavailable', 'field' => 'classroom_id', 'message' => "Room {$room->name} is disabled or unavailable."];
        }

        // Serializing on the tenant's settings row closes the race between conflict checks and inserts.
        $settings = TimetableSetting::lockForUpdate()->first();
        if ($settings) $this->checkSchoolHours($data, $settings, $conflicts);
        $this->checkTeacherAvailability($data, $period, $conflicts);

        foreach ($this->overlappingSessions($data, $period, $except, $exceptIds) as $existing) {
            $from = substr((string) $existing->start_time, 0, 5);
            $to = substr((string) $existing->end_time, 0, 5);
            if ((int) $existing->training_plan_group_id === (int) $data['training_plan_group_id']) {
                $conflicts[] = ['type' => 'group_conflict', 'field' => 'training_plan_group_id', 'message' => "Group {$existing->group->name} already has a session from {$from} to {$to}."];
            }
            if ((int) $existing->teacher_id === (int) $data['teacher_id']) {
                $conflicts[] = ['type' => 'teacher_conflict', 'field' => 'teacher_id', 'message' => "Teacher {$existing->teacher->name} is already teaching {$existing->group->name} from {$from} to {$to}."];
            }
            if ((int) $existing->classroom_id === (int) $data['classroom_id']) {
                $conflicts[] = ['type' => 'room_conflict', 'field' => 'classroom_id', 'message' => "Room {$existing->room->name} is already reserved by {$existing->group->name} from {$from} to {$to}."];
            }
        }

        $groupSize = max((int) $group->students_count, (int) $group->enrollments_count);
        if ($room->capacity < $groupSize) {
            $warnings[] = ['type' => 'room_capacity', 'message' => "Room {$room->name} has capacity {$room->capacity}, below group size {$groupSize}."];
        }
        $requiredTypes = $subject->required_room_types ?? [];
        if ($requiredTypes && ! in_array($room->type->value, $requiredTypes, true)) {
            $warnings[] = ['type' => 'room_type', 'message' => "Subject {$subject->title} requires one of these room types: ".implode(', ', $requiredTypes)."; {$room->name} is {$room->type->value}."];
        }

        return new TimetableConflictResult(array_values(array_unique($conflicts, SORT_REGULAR)), $warnings);
    }

    private function overlappingSessions(array $data, AcademicPeriod $period, ?TimetableSession $except, array $exceptIds): Collection
    {
        $query = TimetableSession::with(['group:id,name', 'teacher:id,name', 'room:id,name'])
            ->where('academic_period_id', $data['academic_period_id'])->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])
            ->where(fn ($q) => $q->where('training_plan_group_id', $data['training_plan_group_id'])->orWhere('teacher_id', $data['teacher_id'])->orWhere('classroom_id', $data['classroom_id']))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))->when($exceptIds, fn ($q) => $q->whereNotIn('id', $exceptIds));

        return $query->lockForUpdate()->get()->filter(fn (TimetableSession $session) => $this->occurrencesOverlap($data, $session, $period));
    }

    private function occurrencesOverlap(array $candidate, TimetableSession $existing, AcademicPeriod $period): bool
    {
        $candidateDate = $candidate['effective_date'] ?? null;
        $existingDate = $existing->effective_date?->toDateString();
        if ($candidateDate && $existingDate) return $candidateDate === $existingDate;
        if ($candidateDate) return Carbon::parse($candidateDate)->dayOfWeekIso === (int) $existing->day;
        if ($existingDate) return Carbon::parse($existingDate)->dayOfWeekIso === (int) $candidate['day'] && Carbon::parse($existingDate)->betweenIncluded($period->starts_on, $period->ends_on);
        return (int) $candidate['day'] === (int) $existing->day;
    }

    private function checkTeacherAvailability(array $data, AcademicPeriod $period, array &$conflicts): void
    {
        $windows = TeacherAvailability::where('teacher_id', $data['teacher_id'])->where('day', $data['day'])->get();
        $positive = $windows->where('is_available', true);
        if ($positive->isNotEmpty() && ! $positive->contains(fn ($w) => $w->start_time <= $data['start_time'] && $w->end_time >= $data['end_time'])) {
            $conflicts[] = ['type' => 'teacher_unavailable', 'field' => 'teacher_id', 'message' => 'The teacher is outside their available hours for this period.'];
        }
        if ($windows->where('is_available', false)->contains(fn ($w) => $w->start_time < $data['end_time'] && $w->end_time > $data['start_time'])) {
            $conflicts[] = ['type' => 'teacher_unavailable', 'field' => 'teacher_id', 'message' => 'The teacher is unavailable during this period.'];
        }
        $dates = $data['effective_date'] ?? null ? [Carbon::parse($data['effective_date'])] : $this->occurrenceDates($period, (int) $data['day']);
        foreach ($dates as $date) {
            $start = $date->copy()->setTimeFromTimeString($data['start_time']);
            $end = $date->copy()->setTimeFromTimeString($data['end_time']);
            $blocked = TeacherUnavailablePeriod::where('teacher_id', $data['teacher_id'])->where('starts_at', '<', $end)->where('ends_at', '>', $start)->first();
            if ($blocked) {
                $conflicts[] = ['type' => 'teacher_unavailable', 'field' => 'teacher_id', 'message' => 'The teacher is unavailable on '.$date->toDateString().($blocked->reason ? ": {$blocked->reason}" : '.')];
                break;
            }
        }
    }

    private function occurrenceDates(AcademicPeriod $period, int $day): array
    {
        $date = Carbon::parse($period->starts_on)->startOfDay();
        while ($date->dayOfWeekIso !== $day) $date->addDay();
        $dates = [];
        while ($date->lte($period->ends_on)) { $dates[] = $date->copy(); $date->addWeek(); }
        return $dates;
    }

    private function checkSchoolHours(array $data, TimetableSetting $settings, array &$conflicts): void
    {
        if (! in_array((int) $data['day'], $settings->working_days, true)) $conflicts[] = ['type' => 'school_closed', 'field' => 'day', 'message' => 'The school is not open on the selected day.'];
        if ($data['start_time'] < $settings->day_starts_at || $data['end_time'] > $settings->day_ends_at) $conflicts[] = ['type' => 'school_hours', 'field' => 'start_time', 'message' => "The session must be within school hours {$settings->day_starts_at}–{$settings->day_ends_at}."];
        foreach ($settings->breaks ?? [] as $break) if (($break['start_time'] ?? '') < $data['end_time'] && ($break['end_time'] ?? '') > $data['start_time']) $conflicts[] = ['type' => 'school_break', 'field' => 'start_time', 'message' => 'The session overlaps the school break '.($break['name'] ?? '').'.'];
        if ($settings->time_slots && ! collect($settings->time_slots)->contains(fn ($slot) => ($slot['start_time'] ?? null) === substr($data['start_time'], 0, 5) && ($slot['end_time'] ?? null) === substr($data['end_time'], 0, 5))) {
            $conflicts[] = ['type' => 'invalid_time_slot', 'field' => 'start_time', 'message' => 'The session does not match a configured school time slot.'];
        }
    }
}
