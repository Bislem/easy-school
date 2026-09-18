<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceExceptionStatus;
use App\Enums\AttendancePersonType;
use App\Enums\SchoolAttendancePermission;
use App\Enums\UserRole;
use App\Events\StudentAbsent;
use App\Events\StudentLate;
use App\Events\TeacherAbsent;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Models\AttendanceException;
use App\Models\AttendanceSetting;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\AttendanceReportingService;
use App\Services\AttendanceService;
use App\Tenancy\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SchoolAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance, AttendanceReportingService $reporting): Response
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
        $siteId = $request->integer('site_id') ?: null;
        $studentId = $request->integer('student_id') ?: null;
        $teacherId = $request->integer('teacher_id') ?: null;
        $justificationStatus = in_array($request->input('justification_status'), ['justified', 'unjustified'], true) ? $request->input('justification_status') : null;
        $groups = SchoolGroup::with(['level.cycle', 'classroom.site'])->where('academic_year_id', $year->id)->where('is_active', true)
            ->when($cycleId, fn ($q) => $q->whereHas('level', fn ($l) => $l->where('school_cycle_id', $cycleId)))
            ->when($levelId, fn ($q) => $q->where('school_level_id', $levelId))
            ->when($siteId, fn ($q) => $q->whereHas('classroom', fn ($room) => $room->where('school_site_id', $siteId)))->orderBy('name')->get();
        if ($groupId && ! $groups->contains('id', $groupId)) {
            $groupId = null;
        }
        $sessions = $view === 'students' && ! $groupId ? collect() : $this->sessions($year, $date, $view === 'students' ? $groupId : null, $view === 'teachers' ? $teacherId : null, $cycleId, $levelId, $view === 'teachers', $siteId);
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
        $teacherImpacts = $view === 'teachers' ? $sessions->filter(fn ($session) => in_array($session->attendance_status, ['ABSENT', 'EXCUSED'], true))
            ->groupBy('teacher_id')->map(fn ($items) => [
                'teacher' => $items->first()->teacher?->name,
                'sessions' => $items->map(fn ($session) => ['id' => $session->id, 'group' => $session->group?->name, 'subject' => $session->subject?->title,
                    'start_time' => substr($session->start_time, 0, 5), 'substitution' => ['status' => 'UNASSIGNED', 'can_assign' => false]])->values(),
            ])->values() : collect();

        $absenceRecords = AttendanceException::with(['student:id,first_name,last_name', 'teacher:id,name', 'timetableSession.subject:id,title', 'timetableSession.group.level.cycle', 'timetableSession.group.classroom.site'])
            ->where('academic_year_id', $year->id)->whereIn('status', ['ABSENT', 'EXCUSED', 'LATE'])
            ->whereDate('date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->whereDate('date', $date)->orWhereDate('end_date', '>=', $date))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))->get()
            ->map(function (AttendanceException $exception) use ($year, $date): array {
                $affected = $exception->timetableSession ? collect([$exception->timetableSession]) : match ($exception->person_type) {
                    AttendancePersonType::TEACHER => $this->sessions($year, $date, null, $exception->teacher_id, forTeachers: true),
                    AttendancePersonType::STUDENT => ($enrollment = StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('student_id', $exception->student_id)->first())
                        ? $this->sessions($year, $date, $enrollment->school_group_id) : collect(),
                };

                return [
                    'id' => $exception->id, 'person_type' => $exception->person_type->value,
                    'person_id' => $exception->student_id ?? $exception->teacher_id,
                    'person' => $exception->student?->full_name ?? $exception->teacher?->name,
                    'status' => $exception->status->value, 'reason' => $exception->reason, 'notes' => $exception->notes,
                    'justification' => $exception->justification,
                    'is_justified' => $exception->status->value === 'EXCUSED' || (bool) $exception->justified_at,
                    'parent_justification_pending' => (bool) $exception->parent_justification_submitted_at && ! $exception->justified_at,
                    'parent_justification_attachment_name' => $exception->parent_justification_attachment_name,
                    'parent_justification_attachment_url' => $exception->parent_justification_attachment_path ? route('admin.school-absence.attachment', $exception->id, false) : null,
                    'scope' => $exception->timetable_session_id ? 'session' : 'day',
                    'sessions' => $affected->map(fn ($session) => ['id' => $session->id, 'group_id' => $session->school_group_id,
                        'level_id' => $session->group?->school_level_id, 'cycle_id' => $session->group?->level?->school_cycle_id,
                        'site_id' => $session->group?->classroom?->school_site_id,
                        'group' => $session->group?->name, 'subject' => $session->subject?->title, 'start_time' => substr($session->start_time, 0, 5)])->values(),
                ];
            })->filter(fn ($record) => (! $groupId || collect($record['sessions'])->contains('group_id', $groupId))
                && (! $levelId || collect($record['sessions'])->contains('level_id', $levelId))
                && (! $cycleId || collect($record['sessions'])->contains('cycle_id', $cycleId))
                && (! $siteId || collect($record['sessions'])->contains('site_id', $siteId))
                && (! $justificationStatus || $record['is_justified'] === ($justificationStatus === 'justified')))->values();

        return Inertia::render('Admin/SchoolAttendance/Index', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']),
            'academicYear' => $year, 'date' => $date->toDateString(), 'view' => $view,
            'filters' => ['cycle_id' => $cycleId, 'level_id' => $levelId, 'group_id' => $groupId, 'site_id' => $siteId,
                'student_id' => $studentId, 'teacher_id' => $teacherId, 'justification_status' => $justificationStatus],
            'cycles' => SchoolCycle::with(['levels' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->orderBy('sort_order')->get(),
            'groups' => $groups, 'sessions' => $sessions, 'absenceRecords' => $absenceRecords,
            'students' => Student::with(['academicEnrollments' => fn ($q) => $q->where('academic_year_id', $year->id)->where('status', 'enrolled')->with(['group:id,name,school_level_id', 'level:id,name'])])->whereHas('academicEnrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('status', 'enrolled'))->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'photo_path'])->map(fn ($student) => ['id' => $student->id, 'first_name' => $student->first_name, 'last_name' => $student->last_name, 'photo_url' => $student->photo_url, 'group' => $student->academicEnrollments->first()?->group?->name, 'level' => $student->academicEnrollments->first()?->level?->name]),
            'teachers' => User::with('taughtSubjects:id,title')->where('role', UserRole::TEACHER->value)->orderBy('name')->get(['id', 'name'])->map(fn ($teacher) => ['id' => $teacher->id, 'name' => $teacher->name, 'photo_url' => null, 'subjects' => $teacher->taughtSubjects->pluck('title')->values()]),
            'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(AttendanceExceptionStatus::cases(), 'value'),
            'dashboard' => $reporting->todayDashboard($year, $date),
            'warnings' => $reporting->warnings($year, $date),
            'attendanceSettings' => AttendanceSetting::current(),
            'teacherImpacts' => $teacherImpacts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::CREATE->value);
        $year = $this->year($request);
        $data = $request->validate($this->rules($year));
        Gate::authorize($data['person_type'] === AttendancePersonType::STUDENT->value
            ? SchoolAttendancePermission::MANAGE_STUDENTS->value
            : SchoolAttendancePermission::MANAGE_TEACHERS->value);
        if (! empty($data['justification'])) {
            Gate::authorize(SchoolAttendancePermission::MANAGE_JUSTIFICATIONS->value);
        }
        $this->save($data, $year, $request->user());

        return back()->with('success', 'Absence enregistrée.');
    }

    public function bulkStudents(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE_STUDENTS->value);
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
        Gate::authorize(SchoolAttendancePermission::MANAGE_TEACHERS->value);
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

    public function sessionOptions(Request $request): JsonResponse
    {
        Gate::authorize(SchoolAttendancePermission::CREATE->value);
        $year = $this->year($request);
        $data = $request->validate([
            'person_type' => ['required', Rule::enum(AttendancePersonType::class)],
            'student_id' => ['nullable', 'integer', TenantRule::exists('students')],
            'teacher_id' => ['nullable', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'date' => ['required', 'date', 'after_or_equal:'.$year->start_date->toDateString(), 'before_or_equal:'.$year->end_date->toDateString()],
        ]);

        $type = AttendancePersonType::from($data['person_type']);
        Gate::authorize($type === AttendancePersonType::STUDENT
            ? SchoolAttendancePermission::MANAGE_STUDENTS->value
            : SchoolAttendancePermission::MANAGE_TEACHERS->value);
        $date = Carbon::parse($data['date']);

        if ($type === AttendancePersonType::STUDENT) {
            if (empty($data['student_id'])) {
                throw ValidationException::withMessages(['student_id' => 'Sélectionnez un élève.']);
            }
            $enrollment = StudentAcademicEnrollment::where('academic_year_id', $year->id)
                ->where('student_id', $data['student_id'])->where('status', 'enrolled')->first();
            $sessions = $enrollment ? $this->sessions($year, $date, $enrollment->school_group_id) : collect();
        } else {
            if (empty($data['teacher_id'])) {
                throw ValidationException::withMessages(['teacher_id' => 'Sélectionnez un enseignant.']);
            }
            $sessions = $this->sessions($year, $date, null, (int) $data['teacher_id'], forTeachers: true);
        }

        return response()->json(['sessions' => $sessions->map(fn (TimetableSession $session) => [
            'id' => $session->id,
            'start_time' => substr($session->start_time, 0, 5),
            'end_time' => substr($session->end_time, 0, 5),
            'subject' => $session->subject?->title,
            'group' => $session->group?->name,
            'room' => $session->room?->name,
        ])->values()]);
    }

    public function destroy(AttendanceException $attendanceException): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::DELETE->value);
        $attendanceException->delete();

        return back()->with('success', 'Absence supprimée : la présence redevient implicite.');
    }

    public function downloadAttachment(Request $request, int $attendanceException)
    {
        Gate::authorize(SchoolAttendancePermission::VIEW->value);
        $absence = AttendanceException::findOrFail($attendanceException);
        abort_unless($absence->parent_justification_attachment_path, 404);

        return Storage::disk('local')->download($absence->parent_justification_attachment_path, $absence->parent_justification_attachment_name ?: 'justificatif');
    }

    private function year(Request $request): AcademicYear
    {
        return ($request->integer('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : null)
            ?? AcademicYear::find($request->session()->get('academic_year_id')) ?? AcademicYear::where('status', 'active')->first()
            ?? AcademicYear::latest('start_date')->first() ?? throw ValidationException::withMessages(['academic_year_id' => 'Sélectionnez une année scolaire.']);
    }

    private function sessions(AcademicYear $year, Carbon $date, ?int $groupId = null, ?int $teacherId = null, ?int $cycleId = null, ?int $levelId = null, bool $forTeachers = false, ?int $siteId = null): Collection
    {
        $dateString = $date->toDateString();
        $audience = $forTeachers ? 'teachers' : 'students';
        if (AcademicYearCalendarEvent::where('academic_year_id', $year->id)->whereDate('starts_on', '<=', $dateString)->whereDate('ends_on', '>=', $dateString)->whereIn('applies_to', ['both', $audience])->exists()) {
            return collect();
        }
        $replaced = TimetableSession::where('academic_year_id', $year->id)->whereDate('effective_date', $dateString)->whereNotNull('parent_session_id')->pluck('parent_session_id');

        return TimetableSession::with(['subject:id,title,title_ar', 'teacher:id,name,email', 'room:id,name', 'group.level.cycle', 'group.classroom.site'])
            ->where('academic_year_id', $year->id)->where('status', '!=', 'cancelled')
            ->when($groupId, fn ($q) => $q->where('school_group_id', $groupId))->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($levelId, fn ($q) => $q->whereHas('group', fn ($g) => $g->where('school_level_id', $levelId)))
            ->when($cycleId, fn ($q) => $q->whereHas('group.level', fn ($l) => $l->where('school_cycle_id', $cycleId)))
            ->when($siteId, fn ($q) => $q->whereHas('group.classroom', fn ($room) => $room->where('school_site_id', $siteId)))
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
        if ($session && ! empty($data['end_date'])) {
            throw ValidationException::withMessages(['end_date' => 'Une absence liée à une séance ne peut pas couvrir une plage de dates.']);
        }
        if ($session && $type === AttendancePersonType::TEACHER && (int) $session->teacher_id !== (int) $data['teacher_id']) {
            throw ValidationException::withMessages(['teacher_id' => "Cet enseignant n'est pas planifié pour cette séance."]);
        }
        if ($type === AttendancePersonType::STUDENT && ! StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('student_id', $data['student_id'])->where('status', 'enrolled')->when($session, fn ($q) => $q->where('school_group_id', $session->school_group_id))->exists()) {
            throw ValidationException::withMessages(['student_id' => "Cet élève n'est pas inscrit dans ce groupe."]);
        }
        if ($session) {
            $date = Carbon::parse($data['date']);
            $scheduled = $type === AttendancePersonType::STUDENT
                ? $this->sessions($year, $date, $session->school_group_id)->contains('id', $session->id)
                : $this->sessions($year, $date, null, (int) $data['teacher_id'], forTeachers: true)->contains('id', $session->id);
            if (! $scheduled) {
                throw ValidationException::withMessages(['timetable_session_id' => "Cette séance ne fait pas partie de l'emploi du temps de la personne à cette date."]);
            }
        }
        $identity = ['academic_year_id' => $year->id, 'date' => $data['date'], 'timetable_session_id' => $session?->id, 'person_type' => $type->value, 'student_id' => $data['student_id'] ?? null, 'teacher_id' => $data['teacher_id'] ?? null];

        $exception = AttendanceException::updateOrCreate($identity, [...$data, 'end_date' => $data['end_date'] ?? null, 'created_by' => $creator->id, 'justified_at' => ! empty($data['justification']) ? now() : null]);
        if ($exception->wasRecentlyCreated || $exception->wasChanged()) {
            match (true) {
                $type === AttendancePersonType::STUDENT && $exception->status->value === 'LATE' => StudentLate::dispatch($exception),
                $type === AttendancePersonType::STUDENT && in_array($exception->status->value, ['ABSENT', 'EXCUSED'], true) => StudentAbsent::dispatch($exception),
                $type === AttendancePersonType::TEACHER && in_array($exception->status->value, ['ABSENT', 'EXCUSED'], true) => TeacherAbsent::dispatch($exception),
                default => null,
            };
        }

        return $exception;
    }
}
