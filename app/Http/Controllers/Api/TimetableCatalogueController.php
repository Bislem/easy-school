<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\SchoolCycle;
use App\Models\TrainingPlanGroup;
use App\Models\User;

class TimetableCatalogueController extends Controller
{
    public function __invoke(): array
    {
        $this->authorize('viewAny', \App\Models\TimetableSession::class);

        return [
            'cycles' => SchoolCycle::with('levels')->orderBy('sort_order')->get(),
            'groups' => TrainingPlanGroup::with(['level.cycle', 'classroom', 'teachers:id,name', 'principalTeacher:id,name'])->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Course::with(['schoolLevels:id', 'teachers:id,name'])->where('is_active', true)->orderBy('title')->get(),
            'teachers' => User::where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'rooms' => Classroom::where('is_active', true)->orderBy('name')->get(),
            'room_types' => array_column(RoomType::cases(), 'value'),
            'academic_periods' => AcademicPeriod::orderByDesc('starts_on')->get(),
        ];
    }
}
