<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolAttendancePermission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AttendanceSetting;
use App\Models\SchoolGroup;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\User;
use App\Services\AttendanceReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolAttendanceReportController extends Controller
{
    public function index(Request $request, AttendanceReportingService $reporting): Response
    {
        Gate::authorize(SchoolAttendancePermission::VIEW->value);
        $year = $this->year($request);
        [$from, $to] = $this->range($request, $year);
        $scope = in_array($request->input('scope'), ['student', 'group', 'teacher'], true) ? $request->input('scope') : 'group';

        return Inertia::render('Admin/SchoolAttendance/Reports', [
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date']),
            'academicYear' => $year,
            'periods' => AcademicPeriod::where('academic_year_id', $year->id)->orderBy('starts_on')->get(['id', 'name', 'starts_on', 'ends_on']),
            'students' => Student::whereHas('academicEnrollments', fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'groups' => SchoolGroup::with('level:id,name')->where('academic_year_id', $year->id)->orderBy('name')->get(['id', 'name', 'school_level_id']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->orderBy('name')->get(['id', 'name']),
            'filters' => ['academic_year_id' => $year->id, 'scope' => $scope, 'entity_id' => $request->integer('entity_id') ?: null,
                'period_type' => $request->input('period_type', 'month'), 'month' => $request->input('month', now()->format('Y-m')), 'academic_period_id' => $request->integer('academic_period_id') ?: null,
                'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => $this->rows($reporting, $year, $scope, $request->integer('entity_id') ?: null, $from, $to),
        ]);
    }

    public function export(Request $request, string $format, AttendanceReportingService $reporting): StreamedResponse|SymfonyResponse
    {
        Gate::authorize(SchoolAttendancePermission::VIEW->value);
        $year = $this->year($request);
        [$from, $to] = $this->range($request, $year);
        $scope = in_array($request->input('scope'), ['student', 'group', 'teacher'], true) ? $request->input('scope') : 'group';
        $rows = $this->rows($reporting, $year, $scope, $request->integer('entity_id') ?: null, $from, $to)->map(fn ($row) => [
            'Nom' => $row['name'], 'Séances prévues' => $row['scheduled_sessions'], 'Présences' => $row['present_sessions'],
            'Absences' => $row['absences'], 'Justifiées' => $row['justified_absences'], 'Non justifiées' => $row['unjustified_absences'],
            'Retards' => $row['late_arrivals'], 'Taux de présence' => $row['attendance_percentage'].'%',
            'Séances affectées' => collect($row['affected_teaching_sessions'] ?? [])->map(fn ($s) => "{$s['date']} {$s['group']} {$s['subject']} {$s['start_time']}")->join(' | '),
        ]);
        $filename = "presences-{$scope}-{$from->format('Ymd')}-{$to->format('Ymd')}";
        if ($format === 'pdf') {
            return Pdf::loadView('admin.reports.table', ['title' => 'Rapport des présences', 'rows' => $rows, 'from' => $from, 'to' => $to])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys($rows->first()), ';');
            }
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize(SchoolAttendancePermission::MANAGE->value);
        $data = $request->validate([
            'monthly_absence_threshold' => ['required', 'integer', 'between:1,100'],
            'consecutive_days_threshold' => ['required', 'integer', 'between:1,30'],
        ]);
        AttendanceSetting::current()->update($data);

        return back()->with('success', 'Seuils d’alerte enregistrés.');
    }

    private function rows(AttendanceReportingService $reporting, AcademicYear $year, string $scope, ?int $entityId, Carbon $from, Carbon $to): Collection
    {
        if ($scope === 'teacher') {
            return User::where('role', UserRole::TEACHER->value)->when($entityId, fn ($q) => $q->whereKey($entityId))->orderBy('name')->get()
                ->map(fn ($teacher) => ['id' => $teacher->id, 'name' => $teacher->name, ...$reporting->teacher($teacher, $year, $from, $to)]);
        }
        $enrollments = StudentAcademicEnrollment::with('student')->where('academic_year_id', $year->id)->where('status', 'enrolled')
            ->when($scope === 'student' && $entityId, fn ($q) => $q->where('student_id', $entityId))
            ->when($scope === 'group' && $entityId, fn ($q) => $q->where('school_group_id', $entityId))->get();

        return $enrollments->filter->student->map(fn ($enrollment) => ['id' => $enrollment->student_id, 'name' => $enrollment->student->full_name,
            ...collect($reporting->student($enrollment->student, $year, $from, $to))->except('absence_dates')->all()])->values();
    }

    private function year(Request $request): AcademicYear
    {
        return AcademicYear::find($request->integer('academic_year_id')) ?? AcademicYear::where('status', 'active')->firstOrFail();
    }

    private function range(Request $request, AcademicYear $year): array
    {
        if ($request->input('period_type') === 'trimester' && $request->integer('academic_period_id')) {
            $period = AcademicPeriod::where('academic_year_id', $year->id)->findOrFail($request->integer('academic_period_id'));

            return [$period->starts_on->copy(), $period->ends_on->copy()];
        }
        if ($request->input('period_type') === 'year') {
            return [$year->start_date->copy(), $year->end_date->copy()];
        }
        $month = Carbon::createFromFormat('Y-m', $request->input('month', now()->format('Y-m')))->startOfMonth();

        return [$month->copy()->max($year->start_date), $month->copy()->endOfMonth()->min($year->end_date)];
    }
}
