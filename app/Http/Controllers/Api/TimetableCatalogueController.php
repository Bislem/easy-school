<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolSubject;
use App\Models\User;

class TimetableCatalogueController extends Controller
{
    public function __invoke(): array
    {
        $this->authorize('viewAny', \App\Models\TimetableSession::class);
        $selectedId = request()->hasSession() ? request()->session()->get('academic_year_id') : null;
        $year = AcademicYear::find($selectedId) ?? AcademicYear::where('status', 'active')->first() ?? AcademicYear::latest('start_date')->first();

        return [
            'academic_year' => $year,
            'cycles' => SchoolCycle::with('levels')->orderBy('sort_order')->get(),
            'groups' => SchoolGroup::with(['level.cycle', 'classroom', 'teachers:id,name', 'principalTeacher:id,name'])->where('is_active', true)->when($year, fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('name')->get(),
            'subjects' => SchoolSubject::with(['schoolLevels:id', 'teachers:id,name,email'])->where('is_active', true)->orderBy('title')->get(),
            'teachers' => User::where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'rooms' => Classroom::where('is_active', true)->orderBy('name')->get(),
            'room_types' => array_column(RoomType::cases(), 'value'),
            'academic_periods' => AcademicPeriod::when($year, fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('number')->get(),
        ];
    }
}
