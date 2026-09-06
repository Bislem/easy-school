<?php

namespace App\Services;

use App\Models\TimetableSession;
use App\Models\TrainingPlanGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TimetableService
{
    public function __construct(private TimetableConflictChecker $conflicts) {}

    public function create(array $data): TimetableSession
    {
        return DB::transaction(function () use ($data) {
            $data = $this->prepare($data);
            $data['series_id'] ??= (string) Str::uuid();
            return $this->persist(new TimetableSession, $data);
        }, 3);
    }

    public function check(array $data, ?TimetableSession $except = null): \App\Support\TimetableConflictResult
    {
        return $this->conflicts->check($this->prepare($data), $except);
    }

    public function update(TimetableSession $session, array $data, string $scope = 'one'): TimetableSession
    {
        if ($scope === 'all') return $this->updateSeries($session, $data);
        if ($session->recurrence->value === 'weekly' && empty($data['effective_date'])) {
            throw ValidationException::withMessages(['effective_date' => 'An occurrence date is required when editing one weekly session. Use scope=all to edit the full series.']);
        }
        return DB::transaction(function () use ($session, $data) {
            $merged = $this->prepare([...$session->only($session->getFillable()), ...$data]);
            if ($session->recurrence->value === 'weekly' && ! empty($data['effective_date'])) {
                $merged['parent_session_id'] = $session->id;
                $merged['recurrence'] = 'once';
                $merged['change_type'] = $data['change_type'] ?? 'exception';
                $merged['series_id'] = $session->series_id;
                return $this->persist(new TimetableSession, $merged, null, [$session->id]);
            }
            return $this->persist($session, $merged, $session);
        }, 3);
    }

    public function updateSeries(TimetableSession $session, array $changes): TimetableSession
    {
        return DB::transaction(function () use ($session, $changes) {
            $members = TimetableSession::where('series_id', $session->series_id)->lockForUpdate()->get();
            $exceptIds = $members->pluck('id')->all();
            $prepared = [];
            foreach ($members as $member) {
                $data = $this->prepare([...$member->only($member->getFillable()), ...$changes]);
                $prepared[$member->id] = $data;
                if (($data['status'] ?? 'draft') !== 'cancelled') $this->throwConflicts($this->conflicts->check($data, $member, $exceptIds)->conflicts);
            }
            foreach ($members as $member) { $member->update($prepared[$member->id]); $this->syncReservation($member->refresh()); }
            return $session->refresh()->load($this->relations());
        }, 3);
    }

    public function duplicate(TimetableSession $session, array $overrides): TimetableSession
    {
        $data = [...$session->only($session->getFillable()), ...$overrides];
        unset($data['parent_session_id']);
        $data['series_id'] = (string) Str::uuid();
        $data['change_type'] = 'duplicate';
        return $this->create($data);
    }

    public function cancel(TimetableSession $session, ?string $effectiveDate = null, string $scope = 'one'): TimetableSession
    {
        if ($scope === 'all') return $this->updateSeries($session, ['status' => 'cancelled', 'change_type' => 'cancelled']);
        if ($session->recurrence->value === 'weekly' && $effectiveDate) return $this->update($session, ['effective_date' => $effectiveDate, 'status' => 'cancelled', 'change_type' => 'cancelled']);
        $session->update(['status' => 'cancelled', 'change_type' => 'cancelled']);
        $this->syncReservation($session);
        return $session->refresh()->load($this->relations());
    }

    private function prepare(array $data): array
    {
        if (empty($data['end_time']) && ! empty($data['start_time'])) {
            $duration = \App\Models\TimetableSetting::first()?->default_session_duration ?? 60;
            $data['end_time'] = \Carbon\Carbon::createFromFormat('H:i', substr($data['start_time'], 0, 5))->addMinutes($duration)->format('H:i');
        }
        if (! empty($data['effective_date'])) $data['day'] = \Carbon\Carbon::parse($data['effective_date'])->dayOfWeekIso;
        if (empty($data['classroom_id'])) {
            $data['classroom_id'] = TrainingPlanGroup::findOrFail($data['training_plan_group_id'])->classroom_id;
        }
        if (empty($data['classroom_id'])) {
            throw ValidationException::withMessages(['classroom_id' => 'Select a location because this group has no default classroom.']);
        }
        return $data;
    }

    private function persist(TimetableSession $session, array $data, ?TimetableSession $except = null, array $exceptIds = []): TimetableSession
    {
        $result = $this->conflicts->check($data, $except, $exceptIds);
        if (($data['status'] ?? 'draft') !== 'cancelled') $this->throwConflicts($result->conflicts);
        $session->fill($data)->save();
        $session->refresh();
        $this->syncReservation($session);
        $session->setAttribute('warnings', $result->warnings);
        return $session->load($this->relations());
    }

    private function throwConflicts(array $conflicts): void
    {
        if ($conflicts) throw ValidationException::withMessages(['conflicts' => array_column($conflicts, 'message')]);
    }

    private function syncReservation(TimetableSession $session): void
    {
        \App\Models\RoomReservation::updateOrCreate(['timetable_session_id' => $session->id], [
            'classroom_id' => $session->classroom_id, 'academic_period_id' => $session->academic_period_id,
            'day' => $session->day, 'effective_date' => $session->effective_date,
            'start_time' => $session->start_time, 'end_time' => $session->end_time,
            'status' => $session->status->value === 'cancelled' ? 'cancelled' : 'reserved',
        ]);
    }

    private function relations(): array { return ['group.level.cycle', 'subject', 'teacher', 'room', 'academicPeriod', 'reservation']; }
}
