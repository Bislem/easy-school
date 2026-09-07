<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceExceptionStatus;
use App\Enums\AttendancePersonType;
use App\Enums\SchoolAttendancePermission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceException;
use App\Models\SchoolGroup;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\AttendanceService;
use App\Tenancy\TenantRule;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $year = $this->selectedYear($request) ?? throw ValidationException::withMessages(['academic_year' => 'Sélectionnez une année scolaire.']);
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        if (! $date->betweenIncluded($year->start_date, $year->end_date)) $date = Carbon::parse($year->start_date);
        $groupId = $request->integer('group_id') ?: null;
        $groups = SchoolGroup::with('level.cycle')->where('academic_year_id', $year->id)->where('is_active', true)->orderBy('name')->get();
        if ($groupId && ! $groups->contains('id', $groupId)) $groupId = null;

        $sessions = collect();
        if ($groupId) {
            $dateString = $date->toDateString();
            $replacedParents = TimetableSession::where('academic_year_id', $year->id)->whereDate('effective_date', $dateString)->whereNotNull('parent_session_id')->pluck('parent_session_id');
            $sessions = TimetableSession::with(['subject:id,title,title_ar', 'teacher:id,name,email', 'room:id,name'])
                ->where('academic_year_id', $year->id)->where('school_group_id', $groupId)->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($date, $dateString, $replacedParents) {
                    $query->where(fn ($weekly) => $weekly->where('recurrence', 'weekly')->where('day', $date->dayOfWeekIso)->whereNull('parent_session_id')->whereNotIn('id', $replacedParents))
                        ->orWhere(fn ($once) => $once->where('recurrence', 'once')->whereDate('effective_date', $dateString));
                })->orderBy('start_time')->get();
            $students = StudentAcademicEnrollment::with('student:id,first_name,last_name,email')
                ->where('academic_year_id', $year->id)->where('school_group_id', $groupId)->where('status', 'enrolled')->get()->pluck('student')->filter()->values();
            $sessions->each(function (TimetableSession $session) use ($attendance, $students, $dateString) {
                $teacherException = $attendance->getTeacherException($session->teacher, $session, $dateString);
                $session->setAttribute('teacher_status', $teacherException?->status->value ?? AttendanceService::PRESENT);
                $session->setAttribute('teacher_exception_id', $teacherException?->id);
                $session->setAttribute('students', $students->map(function (Student $student) use ($attendance, $session, $dateString) {
                    $exception = $attendance->getStudentException($student, $session, $dateString);

                    return [...$student->toArray(), 'attendance_status' => $exception?->status->value ?? AttendanceService::PRESENT, 'attendance_exception_id' => $exception?->id];
                }));
            });
        }

        return Inertia::render('Admin/SchoolAttendance/Index', [
            'academicYear' => $year,
            'date' => $date->toDateString(),
            'selectedGroupId' => $groupId,
            'groups' => $groups,
            'sessions' => $sessions,
            'statuses' => array_column(AttendanceExceptionStatus::cases(), 'value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $year = $this->selectedYear($request) ?? throw ValidationException::withMessages(['academic_year' => 'Sélectionnez une année scolaire.']);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:'.$year->start_date->toDateString(), 'before_or_equal:'.$year->end_date->toDateString()],
            'end_date' => ['nullable', 'date', 'after_or_equal:date', 'before_or_equal:'.$year->end_date->toDateString()],
            'timetable_session_id' => ['nullable', 'integer', TenantRule::exists('timetable_sessions')->where('academic_year_id', $year->id)],
            'person_type' => ['required', Rule::enum(AttendancePersonType::class)],
            'student_id' => ['nullable', 'integer', TenantRule::exists('students')],
            'teacher_id' => ['nullable', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'status' => ['required', Rule::enum(AttendanceExceptionStatus::class)],
            'minutes_late' => ['nullable', 'integer', 'between:1,1440'],
            'reason' => ['nullable', 'string', 'max:255'], 'justification' => ['nullable', 'string', 'max:5000'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $type = AttendancePersonType::from($data['person_type']);
        if (($type === AttendancePersonType::STUDENT) !== ! empty($data['student_id']) || ($type === AttendancePersonType::TEACHER) !== ! empty($data['teacher_id'])) {
            throw ValidationException::withMessages(['person_type' => 'Sélectionnez exactement la personne correspondant au type de présence.']);
        }
        if ($type === AttendancePersonType::STUDENT && ! empty($data['end_date'])) throw ValidationException::withMessages(['end_date' => "Une absence d'élève ne peut pas être une plage."]);
        $session = ! empty($data['timetable_session_id']) ? TimetableSession::findOrFail($data['timetable_session_id']) : null;
        if ($session && $type === AttendancePersonType::TEACHER && (int) $session->teacher_id !== (int) $data['teacher_id']) throw ValidationException::withMessages(['teacher_id' => "Cet enseignant n'est pas planifié pour cette séance."]);
        if ($type === AttendancePersonType::STUDENT && ! StudentAcademicEnrollment::where('academic_year_id', $year->id)->where('student_id', $data['student_id'])->when($session, fn ($q) => $q->where('school_group_id', $session->school_group_id))->exists()) {
            throw ValidationException::withMessages(['student_id' => "Cet élève n'est pas inscrit dans ce groupe pour l'année scolaire."]);
        }
        $identity = ['academic_year_id' => $year->id, 'date' => $data['date'], 'timetable_session_id' => $session?->id, 'person_type' => $type->value, 'student_id' => $data['student_id'] ?? null, 'teacher_id' => $data['teacher_id'] ?? null];
        AttendanceException::updateOrCreate($identity, [...$data, 'end_date' => $data['end_date'] ?? null, 'created_by' => $request->user()->id, 'justified_at' => ! empty($data['justification']) ? now() : null]);

        return back()->with('success', 'Exception de présence enregistrée.');
    }

    public function destroy(AttendanceException $attendanceException): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $attendanceException->delete();

        return back()->with('success', 'Présence rétablie.');
    }

    private function selectedYear(Request $request): ?AcademicYear
    {
        return AcademicYear::find($request->session()->get('academic_year_id')) ?? AcademicYear::where('status', 'active')->first() ?? AcademicYear::latest('start_date')->first();
    }
}
