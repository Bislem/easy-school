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
        return TimetableSetting::first() ?? TimetableSetting::make(TimetableSetting::defaults());
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
}
