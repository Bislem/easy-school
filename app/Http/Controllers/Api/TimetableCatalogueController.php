<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\SchoolCycle;
use App\Models\TrainingPlanGroup;
use App\Models\User;
use App\Enums\UserRole;

class TimetableCatalogueController extends Controller
{
    public function __invoke(): array
    {
        $this->authorize('viewAny', \App\Models\TimetableSession::class);
        return [
            'cycles' => SchoolCycle::with('levels')->orderBy('sort_order')->get(),
            'groups' => TrainingPlanGroup::with(['level.cycle', 'classroom'])->orderBy('name')->get(),
            'subjects' => Course::with('schoolLevels:id')->where('is_active', true)->orderBy('title')->get(),
            'teachers' => User::where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'rooms' => Classroom::where('is_active', true)->orderBy('name')->get(),
            'room_types' => array_column(RoomType::cases(), 'value'),
            'academic_periods' => AcademicPeriod::orderByDesc('starts_on')->get(),
        ];
    }
}
