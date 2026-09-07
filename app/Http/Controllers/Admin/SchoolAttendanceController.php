<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceExceptionStatus;
use App\Enums\AttendancePersonType;
use App\Enums\SchoolAttendancePermission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Models\AttendanceException;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Tenancy\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchoolAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance): Response
    {
        Gate::authorize(SchoolAttendancePermission::VIEW->value);
        $year = $this->year($request);
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        if (! $date->betweenIncluded($year->start_date, $year->end_date)) {
            $date = $year->start_date->copy();
        }
        $view = $request->input('view') === 'teachers' ? 'teachers' : 'students';
        $cycleId = $request->integer('cycle_id') ?: null;
        $levelId = $request->integer('level_id') ?: null;
        $groupId = $request->integer('group_id') ?: null;
        $groups = SchoolGroup::with('level.cycle')->where('academic_year_id', $year->id)->where('is_active', true)
            ->when($cycleId, fn ($q) => $q->whereHas('level', fn ($l) => $l->where('school_cycle_id', $cycleId)))
            ->when($levelId, fn ($q) => $q->where('school_level_id', $levelId))->orderBy('name')->get();
        if ($groupId && ! $groups->contains('id', $groupId)) {
            $groupId = null;
        }
        $sessions = $view === 'students' && ! $groupId ? collect() : $this->sessions($year, $date, $view === 'students' ? $groupId : null, null, $cycleId, $levelId, $view === 'teachers');
        $students = $groupId ? StudentAcademicEnrollment::with('student:id,first_name,last_name,email')
            ->where('academic_year_id', $year->id)->where('school_group_id', $groupId)->where('status', 'enrolled')
            ->get()->pluck('student')->filter()->values() : collect();
        $sessions->each(function (TimetableSession $session) use ($attendance, $students, $date, $view): void {
            if ($session->teacher) {
                $exception = $attendance->getTeacherException($session->teacher, $session, $date);
                $session->setAttribute('attendance_status', $exception?->status->value ?? AttendanceService::PRESENT);
                $session->setAttribute('attendance_exception', $exception);
            }
            if ($view === 'students') {
                $session->setAttribute('students', $students->map(function (Student $student) use ($attendance, $session, $date): array {
                    $exception = $attendance->getStudentException($student, $session, $date);

                    return [...$student->toArray(), 'attendance_status' => $exception?->status->value ?? AttendanceService::PRESENT, 'attendance_exception' => $exception];
                }));
            }
        });

        return Inertia::render('Admin/SchoolAttendance/Index', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'academicYear' => $year, 'date' => $date->toDateString(), 'view' => $view,
            'filters' => ['cycle_id' => $cycleId, 'level_id' => $levelId, 'group_id' => $groupId],
            'cycles' => SchoolCycle::with(['levels' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->orderBy('sort_order')->get(),
            'groups' => $groups, 'sessions' => $sessions,
            'statuses' => array_column(AttendanceExceptionStatus::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $year = $this->year($request);
        $this->save($request->validate($this->rules($year)), $year, $request->user());

        return back()->with('success', 'Exception de présence enregistrée.');
    }

    public function bulkStudents(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $year = $this->year($request);
        $data = $request->validate([
            'date' => ['required', 'date'], 'timetable_session_id' => ['nullable', 'integer', TenantRule::exists('timetable_sessions')->where('academic_year_id', $year->id)],
            'student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer', 'distinct', TenantRule::exists('students')], 'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $session = isset($data['timetable_session_id']) ? TimetableSession::findOrFail($data['timetable_session_id']) : null;
        $valid = StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('status', 'enrolled')
            ->when($session, fn ($q) => $q->where('school_group_id', $session->school_group_id))->whereIn('student_id', $data['student_ids'])->pluck('student_id');
        if ($valid->count() !== count($data['student_ids'])) {
            throw ValidationException::withMessages(['student_ids' => "Un élève sélectionné n'est pas inscrit dans ce groupe."]);
        }
        foreach ($valid as $studentId) {
            $this->save(['date' => $data['date'], 'timetable_session_id' => $session?->id, 'person_type' => 'STUDENT', 'student_id' => $studentId, 'status' => 'ABSENT', 'reason' => $data['reason'] ?? null], $year, $request->user());
        }

        return back()->with('success', 'Élèves sélectionnés marqués absents.');
    }

    public function teacherPreview(Request $request): JsonResponse
    {
        Gate::authorize(SchoolAttendancePermission::VIEW->value);
        $year = $this->year($request);
        $data = $request->validate([
            'teacher_id' => ['required', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'date' => ['required', 'date', 'after_or_equal:'.$year->start_date->toDateString()],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:'.$year->end_date->toDateString()],
        ]);
        $cursor = Carbon::parse($data['date']);
        $end = Carbon::parse($data['end_date'] ?? $data['date']);
        $result = collect();
        while ($cursor->lte($end)) {
            $day = $cursor->copy();
            $result->push(...$this->sessions($year, $day, null, (int) $data['teacher_id'], null, null, true)->map(fn ($s) => [
                'id' => $s->id, 'date' => $day->toDateString(), 'start_time' => $s->start_time, 'end_time' => $s->end_time,
                'subject' => $s->subject?->title, 'group' => $s->group?->name,
            ]));
            $cursor->addDay();
        }

        return response()->json(['sessions' => $result]);
    }

    public function destroy(AttendanceException $attendanceException): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $attendanceException->delete();

        return back()->with('success', 'Présence rétablie.');
    }

    private function year(Request $request): AcademicYear
    {
        return ($request->integer('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null)
            ?? AcademicYear::find($request->session()->get('academic_year_id')) ?? AcademicYear::where('status', 'active')->first()
            ?? AcademicYear::latest('start_date')->first() ?? throw ValidationException::withMessages(['academic_year_id' => 'Sélectionnez une année scolaire.']);
    }

    private function sessions(AcademicYear $year, Carbon $date, ?int $groupId = null, ?int $teacherId = null, ?int $cycleId = null, ?int $levelId = null, bool $forTeachers = false): Collection
    {
        $dateString = $date->toDateString();
        $audience = $forTeachers ? 'teachers' : 'students';
        if (AcademicYearCalendarEvent::where('academic_year_id', $year->id)->whereDate('starts_on', '<=', $dateString)->whereDate('ends_on', '>=', $dateString)->whereIn('applies_to', ['both', $audience])->exists()) {
            return collect();
        }
        $replaced = TimetableSession::where('academic_year_id', $year->id)->whereDate('effective_date', $dateString)->whereNotNull('parent_session_id')->pluck('parent_session_id');

        return TimetableSession::with(['subject:id,title,title_ar', 'teacher:id,name,email', 'room:id,name', 'group.level.cycle'])
            ->where('academic_year_id', $year->id)->where('status', '!=', 'cancelled')
            ->when($groupId, fn ($q) => $q->where('school_group_id', $groupId))->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($levelId, fn ($q) => $q->whereHas('group', fn ($g) => $g->where('school_level_id', $levelId)))
            ->when($cycleId, fn ($q) => $q->whereHas('group.level', fn ($l) => $l->where('school_cycle_id', $cycleId)))
            ->where(fn ($q) => $q->where(fn ($weekly) => $weekly->where('recurrence', 'weekly')->where('day', $date->dayOfWeekIso)->whereNull('parent_session_id')->whereNotIn('id', $replaced))
                ->orWhere(fn ($once) => $once->where('recurrence', 'once')->whereDate('effective_date', $dateString)))
            ->orderBy('start_time')->get();
    }

    private function rules(AcademicYear $year): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:'.$year->start_date->toDateString(), 'before_or_equal:'.$year->end_date->toDateString()],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:'.$year->end_date->toDateString()],
            'timetable_session_id' => ['nullable', 'integer', TenantRule::exists('timetable_sessions')->where('academic_year_id', $year->id)],
            'person_type' => ['required', Rule::enum(AttendancePersonType::class)], 'student_id' => ['nullable', 'integer', TenantRule::exists('students')],
            'teacher_id' => ['nullable', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)], 'status' => ['required', Rule::enum(AttendanceExceptionStatus::class)],
            'minutes_late' => ['nullable', 'integer', 'between:1,1440', 'required_if:status,LATE'], 'reason' => ['nullable', 'string', 'max:255'],
            'justification' => ['nullable', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function save(array $data, AcademicYear $year, User $creator): AttendanceException
    {
        $type = AttendancePersonType::from($data['person_type']);
        if (($type === AttendancePersonType::STUDENT) !== ! empty($data['student_id']) || ($type === AttendancePersonType::TEACHER) !== ! empty($data['teacher_id'])) {
            throw ValidationException::withMessages(['person_type' => 'Sélectionnez exactement la personne correspondant au type.']);
        }
        if ($type === AttendancePersonType::STUDENT && ! empty($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => "Une absence d'élève ne peut pas être une plage."]);
        }
        $session = ! empty($data['timetable_session_id']) ? TimetableSession::findOrFail($data['timetable_session_id']) : null;
        if ($session && $type === AttendancePersonType::TEACHER && (int) $session->teacher_id !== (int) $data['teacher_id']) {
            throw ValidationException::withMessages(['teacher_id' => "Cet enseignant n'est pas planifié pour cette séance."]);
        }
        if ($type === AttendancePersonType::STUDENT && ! StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('student_id', $data['student_id'])->where('status', 'enrolled')->when($session, fn ($q) => $q->where('school_group_id', $session->school_group_id))->exists()) {
            throw ValidationException::withMessages(['student_id' => "Cet élève n'est pas inscrit dans ce groupe."]);
        }
        $identity = ['academic_year_id' => $year->id, 'date' => $data['date'], 'timetable_session_id' => $session?->id, 'person_type' => $type->value, 'student_id' => $data['student_id'] ?? null, 'teacher_id' => $data['teacher_id'] ?? null];

        return AttendanceException::updateOrCreate($identity, [...$data, 'end_date' => $data['end_date'] ?? null, 'created_by' => $creator->id, 'justified_at' => ! empty($data['justification']) ? now() : null]);
    }
}
