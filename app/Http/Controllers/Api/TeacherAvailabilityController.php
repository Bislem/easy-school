<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\TeacherAvailability;
use App\Models\TeacherUnavailablePeriod;
use App\Tenancy\TenantRule;
use Illuminate\Http\Request;

class TeacherAvailabilityController extends Controller
{
    public function index(Request $request, int $teacher)
    {
        $request->user()->can('timetable.view') || abort(403);
        validator(['teacher_id' => $teacher], ['teacher_id' => [TenantRule::exists('users')->where('role', UserRole::TEACHER->value)]])->validate();
        return ['weekly' => TeacherAvailability::where('teacher_id', $teacher)->orderBy('day')->orderBy('start_time')->get(), 'unavailable_periods' => TeacherUnavailablePeriod::where('teacher_id', $teacher)->orderBy('starts_at')->get()];
    }

    public function storeWeekly(Request $request, int $teacher)
    {
        $request->user()->can('timetable.manage') || abort(403);
        $data = $request->validate(['day' => ['required', 'integer', 'between:1,7'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'is_available' => ['required', 'boolean']]);
        validator(['teacher_id' => $teacher], ['teacher_id' => [TenantRule::exists('users')->where('role', UserRole::TEACHER->value)]])->validate();
        return response()->json(TeacherAvailability::create([...$data, 'teacher_id' => $teacher]), 201);
    }

    public function storeUnavailable(Request $request, int $teacher)
    {
        $request->user()->can('timetable.manage') || abort(403);
        $data = $request->validate(['starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'reason' => ['nullable', 'string', 'max:255']]);
        validator(['teacher_id' => $teacher], ['teacher_id' => [TenantRule::exists('users')->where('role', UserRole::TEACHER->value)]])->validate();
        return response()->json(TeacherUnavailablePeriod::create([...$data, 'teacher_id' => $teacher]), 201);
    }

    public function destroyWeekly(Request $request, TeacherAvailability $availability)
    {
        $request->user()->can('timetable.manage') || abort(403); $availability->delete(); return response()->noContent();
    }
    public function destroyUnavailable(Request $request, TeacherUnavailablePeriod $unavailablePeriod)
    {
        $request->user()->can('timetable.manage') || abort(403); $unavailablePeriod->delete(); return response()->noContent();
    }
}
