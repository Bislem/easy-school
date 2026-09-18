<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\StudentAcademicEnrollment;
use App\Services\AcademicGradeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GradebookController extends Controller
{
    public function index(Request $request, AcademicGradeService $calculator)
    {
        $year = AcademicYear::find($request->integer('academic_year_id')) ?? AcademicYear::where('status', 'active')->latest('start_date')->first();
        $filters = $request->only(['academic_year_id', 'academic_period_id', 'school_level_id', 'school_group_id', 'subject_id', 'search', 'incomplete']);
        $groups = SchoolGroup::when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when($request->integer('school_level_id'), fn ($q, $levelId) => $q->where('school_level_id', $levelId))
            ->where('is_active', true)->orderBy('name')->get(['id', 'name', 'school_level_id']);
        $subjects = collect();
        $assessments = collect();
        $matrix = [];
        $summary = ['students' => 0, 'subjects' => 0, 'assessments' => 0, 'grades_percent' => 0, 'complete' => 0, 'incomplete' => 0, 'average' => null, 'readiness' => 0];
        $group = $groups->firstWhere('id', $request->integer('school_group_id'));
        $period = $year ? AcademicPeriod::where('academic_year_id', $year->id)->find($request->integer('academic_period_id')) : null;

        if ($group && $period) {
            $enrollments = StudentAcademicEnrollment::with('student:id,first_name,last_name,registration_number')->where('academic_year_id', $year->id)->where('school_group_id', $group->id)->get()->sortBy(fn ($e) => $e->student?->registration_number ?? $e->student?->last_name)->values();
            $assessments = Assessment::with('subject:id,title')->where('tenant_id', app(\App\Tenancy\TenantContext::class)->id())->where('academic_year_id', $year->id)->where('academic_period_id', $period->id)->where('school_level_id', $group->school_level_id)->whereHas('groups', fn ($q) => $q->whereKey($group->id))->orderBy('assessment_date')->get();
            if ($filters['subject_id'] ?? null) {
                $assessments = $assessments->where('subject_id', (int) $filters['subject_id'])->values();
            }
            $subjects = $assessments->pluck('subject')->filter()->unique('id')->values();
            $coefficients = $group->level->subjects()->wherePivot('is_active', true)->pluck('coefficient', 'courses.id');
            $subjects->each(fn ($subject) => $subject->setAttribute('coefficient', (float) ($coefficients[$subject->id] ?? 1)));
            $grades = Grade::whereIn('assessment_id', $assessments->pluck('id'))->whereIn('student_academic_enrollment_id', $enrollments->pluck('id'))->get()->groupBy('student_academic_enrollment_id');

            foreach ($enrollments as $enrollment) {
                $studentGrades = $grades->get($enrollment->id, collect());
                $cells = [];
                $weighted = 0.0;
                $coefficientTotal = 0.0;
                $incomplete = 0;
                foreach ($subjects as $subject) {
                    $subjectAssessments = $assessments->where('subject_id', $subject->id)->values();
                    $result = $calculator->subjectResult($subjectAssessments, $studentGrades->whereIn('assessment_id', $subjectAssessments->pluck('id')));
                    $state = $result['average'] !== null ? (string) $result['average'] : ($result['has_exemption'] ? 'Dispensé' : ($result['has_absence'] ? 'Absent' : '—'));
                    $cells[$subject->id] = $result + ['state' => $state, 'coefficient' => (float) ($coefficients[$subject->id] ?? 1)];
                    if ($result['missing'] > 0) {
                        $incomplete++;
                    }
                    if ($result['average'] !== null) {
                        $coefficient = (float) ($coefficients[$subject->id] ?? 1);
                        $weighted += $result['average'] * $coefficient;
                        $coefficientTotal += $coefficient;
                    }
                }
                $matrix[$enrollment->id] = ['student' => $enrollment->student, 'cells' => $cells, 'average' => $coefficientTotal > 0 ? round($weighted / $coefficientTotal, 2) : null, 'incomplete' => $incomplete];
            }
            if ($filters['search'] ?? null) {
                $needle = strtolower((string) $filters['search']);
                $matrix = collect($matrix)->filter(fn ($row) => str_contains(strtolower(($row['student']->first_name ?? '').' '.($row['student']->last_name ?? '').' '.($row['student']->registration_number ?? '')), $needle))->all();
            }
            if (($filters['incomplete'] ?? null) === '1') {
                $matrix = collect($matrix)->filter(fn ($row) => $row['incomplete'] > 0)->all();
            }
            $expected = $assessments->count() * $enrollments->count();
            $entered = Grade::whereIn('assessment_id', $assessments->pluck('id'))->whereIn('student_academic_enrollment_id', $enrollments->pluck('id'))->whereIn('status', ['graded', 'absent', 'exempted', 'absent_with_zero'])->count();
            $rows = collect($matrix);
            $average = $rows->pluck('average')->filter(fn ($value) => $value !== null)->avg();
            $summary = ['students' => $enrollments->count(), 'subjects' => $subjects->count(), 'assessments' => $assessments->count(), 'grades_percent' => $expected ? round($entered / $expected * 100) : 0, 'complete' => $rows->where('incomplete', 0)->count(), 'incomplete' => $rows->where('incomplete', '>', 0)->count(), 'average' => $average !== null ? round((float) $average, 2) : null, 'readiness' => $expected ? round($entered / $expected * 100) : 0];
        }

        return Inertia::render('Admin/Gradebook/Index', ['academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']), 'academicYear' => $year, 'periods' => AcademicPeriod::when($year, fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('number')->get(['id', 'name', 'number']), 'levels' => SchoolLevel::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'specialization']), 'groups' => $groups, 'subjects' => $subjects, 'assessments' => $assessments, 'matrix' => $matrix, 'summary' => $summary, 'filters' => $filters]);
    }
}
