<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimetableSetting;
use Illuminate\Http\Request;

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
            'breaks' => ['present', 'array'], 'breaks.*.name' => ['required', 'string', 'max:100'], 'breaks.*.start_time' => ['required', 'date_format:H:i'], 'breaks.*.end_time' => ['required', 'date_format:H:i'],
            'time_slots' => ['present', 'array'], 'time_slots.*.start_time' => ['required', 'date_format:H:i'], 'time_slots.*.end_time' => ['required', 'date_format:H:i'],
        ]);
        return TimetableSetting::updateOrCreate([], $data);
    }
}
