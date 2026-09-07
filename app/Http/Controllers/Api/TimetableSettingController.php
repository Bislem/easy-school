<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\TimetableSetting;
use App\Models\SchoolGroup;
use App\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableSettingController extends Controller
{
    public function show(Request $request)
    {
        $request->user()->can('timetable.view') || abort(403);
        $settings = TimetableSetting::first() ?? TimetableSetting::make(TimetableSetting::defaults());
        $settings->time_slots = $this->buildTimeSlots(
            substr($settings->day_starts_at, 0, 5),
            substr($settings->day_ends_at, 0, 5),
            $settings->default_session_duration,
            $settings->breaks ?? [],
        );

        return $settings;
    }

    public function update(Request $request)
    {
        $request->user()->can('timetable.manage') || abort(403);
        $data = $request->validate([
            'working_days' => ['required', 'array', 'min:1'], 'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'day_starts_at' => ['required', 'date_format:H:i'], 'day_ends_at' => ['required', 'date_format:H:i', 'after:day_starts_at'],
            'default_session_duration' => ['required', 'integer', 'between:5,480'],
            'breaks' => ['present', 'array'], 'breaks.*.name' => ['required', 'string', 'max:100'], 'breaks.*.type' => ['sometimes', 'in:break,meal'], 'breaks.*.start_time' => ['required', 'date_format:H:i'], 'breaks.*.end_time' => ['required', 'date_format:H:i', 'after:breaks.*.start_time'],
            'time_slots' => ['present', 'array'], 'time_slots.*.start_time' => ['required', 'date_format:H:i'], 'time_slots.*.end_time' => ['required', 'date_format:H:i'],
        ]);
        $data['time_slots'] = $this->buildTimeSlots($data['day_starts_at'], $data['day_ends_at'], $data['default_session_duration'], $data['breaks']);

        return TimetableSetting::updateOrCreate([], $data);
    }

    public function updateGroupDefaults(Request $request, SchoolGroup $group)
    {
        $request->user()->can('timetable.manage') || abort(403);
        $data = $request->validate([
            'classroom_id' => ['nullable', TenantRule::exists('classrooms')->where('is_active', true)->where('is_available', true)],
            'principal_teacher_id' => ['nullable', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)->where('is_active', true)],
        ]);
        if (! empty($data['classroom_id'])) {
            $room = Classroom::findOrFail($data['classroom_id']);
            if ($group->capacity && $room->capacity < $group->capacity) {
                throw ValidationException::withMessages(['classroom_id' => "La salle ne peut pas accueillir la capacité configurée du groupe ({$group->capacity})."]);
            }
        }
        $group->update($data);
        if (! empty($data['principal_teacher_id']) && ! $group->teachers()->whereKey($data['principal_teacher_id'])->exists()) {
            $group->teachers()->attach($data['principal_teacher_id']);
        }
        return $group->refresh()->load(['level.cycle', 'classroom', 'teachers:id,name', 'principalTeacher:id,name']);
    }

    private function buildTimeSlots(string $start, string $end, int $duration, array $breaks): array
    {
        $minutes = fn (string $time): int => ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
        $format = fn (int $value): string => sprintf('%02d:%02d', intdiv($value, 60), $value % 60);
        $cursor = $minutes($start);
        $dayEnd = $minutes($end);
        $pauses = collect($breaks)->sortBy('start_time')->values();
        $slots = [];
        while ($cursor < $dayEnd) {
            $pause = $pauses->first(fn ($item) => $minutes($item['start_time']) === $cursor);
            $slotEnd = $pause ? min($dayEnd, $minutes($pause['end_time'])) : min($cursor + $duration, $dayEnd);
            if (! $pause) {
                $nextPause = $pauses->first(fn ($item) => $minutes($item['start_time']) > $cursor && $minutes($item['start_time']) < $slotEnd);
                if ($nextPause) {
                    $slotEnd = $minutes($nextPause['start_time']);
                }
            }
            if ($slotEnd <= $cursor) {
                break;
            }
            $slots[] = ['start_time' => $format($cursor), 'end_time' => $format($slotEnd)];
            $cursor = $slotEnd;
        }

        return $slots;
    }
}
