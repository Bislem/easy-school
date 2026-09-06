<?php

namespace App\Repositories;

use App\Models\TimetableSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TimetableSessionRepository
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return TimetableSession::query()->with(['group.level.cycle', 'subject', 'teacher', 'room', 'academicPeriod'])
            ->when($filters['academic_period_id'] ?? null, fn (Builder $q, $id) => $q->where('academic_period_id', $id))
            ->when($filters['group_id'] ?? null, fn (Builder $q, $id) => $q->where('training_plan_group_id', $id))
            ->when($filters['teacher_id'] ?? null, fn (Builder $q, $id) => $q->where('teacher_id', $id))
            ->when($filters['day'] ?? null, fn (Builder $q, $day) => $q->where('day', $day))
            ->orderBy('day')->orderBy('start_time')->paginate($perPage);
    }

    public function conflicts(array $data, ?TimetableSession $except = null): Builder
    {
        return TimetableSession::query()
            ->where('academic_period_id', $data['academic_period_id'])->where('day', $data['day'])
            ->where('status', '!=', 'cancelled')->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])
            ->where(fn (Builder $q) => $q->where('training_plan_group_id', $data['training_plan_group_id'])
                ->orWhere('teacher_id', $data['teacher_id'])->orWhere('classroom_id', $data['classroom_id']))
            ->when($except, fn (Builder $q) => $q->whereKeyNot($except->getKey()));
    }

    public function calendar(string $from, string $to, array $filters = []): Collection
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $sessions = TimetableSession::with(['group.level.cycle', 'subject', 'teacher', 'room', 'academicPeriod'])
            ->whereHas('academicPeriod', fn ($q) => $q->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start))
            ->when($filters['group_id'] ?? null, fn ($q, $id) => $q->where('training_plan_group_id', $id))
            ->when($filters['teacher_id'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))->get();
        $exceptions = $sessions->whereNotNull('effective_date')->keyBy(fn ($s) => ($s->parent_session_id ?: $s->id).'|'.$s->effective_date->toDateString());
        $items = collect();
        foreach ($sessions as $session) {
            if ($session->effective_date) {
                if ($session->status->value !== 'cancelled' && $session->effective_date->betweenIncluded($start, $end)) $items->push($this->occurrence($session, $session->effective_date));
                continue;
            }
            if ($session->status->value === 'cancelled') continue;
            if ($session->recurrence->value === 'once') continue;
            $date = $start->copy();
            while ($date->dayOfWeekIso !== $session->day) $date->addDay();
            while ($date->lte($end)) {
                if ($date->betweenIncluded($session->academicPeriod->starts_on, $session->academicPeriod->ends_on) && ! $exceptions->has($session->id.'|'.$date->toDateString())) $items->push($this->occurrence($session, $date));
                $date->addWeek();
            }
        }
        return $items->sortBy(fn ($item) => $item['occurrence_date'].' '.$item['start_time'])->values();
    }

    private function occurrence(TimetableSession $session, Carbon $date): array
    {
        return ['id' => $session->id, 'series_id' => $session->series_id, 'occurrence_date' => $date->toDateString(), 'academic_period_id' => $session->academic_period_id,
            'start_time' => $session->start_time, 'end_time' => $session->end_time, 'status' => $session->status,
            'change_type' => $session->change_type, 'group' => $session->group, 'subject' => $session->subject,
            'teacher' => $session->teacher, 'room' => $session->room];
    }
}
