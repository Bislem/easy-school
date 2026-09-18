<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Collection;

final class ParentAcademicResultsService
{
    public function publishedResults(StudentAcademicEnrollment $enrollment, ?AcademicPeriod $period = null, ?int $subjectId = null): Collection
    {
        $assessments = Assessment::with(['subject:id,title', 'teacher:id,name', 'period:id,name,number'])
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('school_level_id', $enrollment->school_level_id)
            ->whereNotNull('published_at')
            ->whereHas('groups', fn ($query) => $query->whereKey($enrollment->school_group_id))
            ->when($period, fn ($query) => $query->where('academic_period_id', $period->id))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->orderBy('assessment_date')->get();
        $grades = Grade::where('student_academic_enrollment_id', $enrollment->id)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->whereIn('status', ['graded', 'absent', 'exempted', 'absent_with_zero'])
            ->get()->keyBy('assessment_id');
        $coefficients = $enrollment->level->subjects()->wherePivot('is_active', true)->pluck('coefficient', 'courses.id');

        return $assessments->groupBy('subject_id')->map(function (Collection $subjectAssessments, int $subjectId) use ($grades, $coefficients) {
            $subjectGrades = $grades->whereIn('assessment_id', $subjectAssessments->pluck('id'));
            $calculation = app(AcademicGradeService::class)->subjectResult($subjectAssessments, $subjectGrades);
            $first = $subjectAssessments->first();

            return [
                'subject' => ['id' => $subjectId, 'name' => $first->subject?->title, 'coefficient' => (float) ($coefficients[$subjectId] ?? 1)],
                'average' => $calculation['average'],
                'assessments' => $subjectAssessments->map(function (Assessment $assessment) use ($grades) {
                    $grade = $grades->get($assessment->id);
                    if (! $grade) return null;

                    return [
                        'id' => $assessment->id,
                        'name' => $assessment->name,
                        'type' => $assessment->assessment_type,
                        'type_label' => config("assessments.types.{$assessment->assessment_type}.label", $assessment->assessment_type),
                        'date' => $assessment->assessment_date?->format('Y-m-d'),
                        'maximum_grade' => (float) $assessment->maximum_grade,
                        'weight' => (float) $assessment->weight,
                        'teacher' => $assessment->teacher?->name,
                        'period' => $assessment->period?->name,
                        'grade' => $grade->status === 'graded' ? (float) $grade->value : ($grade->status === 'absent_with_zero' ? 0.0 : null),
                        'grade_status' => $grade->status,
                        'published_at' => $assessment->published_at?->toIso8601String(),
                    ];
                })->filter()->sortByDesc('date')->values(),
            ];
        })->values();
    }

    public function exams(StudentAcademicEnrollment $enrollment, ?AcademicPeriod $period = null, ?int $subjectId = null): Collection
    {
        $assessments = Assessment::with(['subject:id,title', 'teacher:id,name', 'period:id,name,number'])
            ->where('academic_year_id', $enrollment->academic_year_id)->where('school_level_id', $enrollment->school_level_id)
            ->where('assessment_type', 'exam')->where('status', '!=', 'draft')->whereNotNull('assessment_date')
            ->whereHas('groups', fn ($query) => $query->whereKey($enrollment->school_group_id))
            ->when($period, fn ($query) => $query->where('academic_period_id', $period->id))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->orderBy('assessment_date')->get();
        $grades = Grade::where('student_academic_enrollment_id', $enrollment->id)
            ->whereIn('assessment_id', $assessments->whereNotNull('published_at')->pluck('id'))->get()->keyBy('assessment_id');

        return $assessments->map(function (Assessment $assessment) use ($grades) {
            $grade = $grades->get($assessment->id);
            return [
                'id' => $assessment->id, 'name' => $assessment->name, 'subject' => $assessment->subject?->title,
                'subject_id' => $assessment->subject_id, 'date' => $assessment->assessment_date?->format('Y-m-d'),
                'teacher' => $assessment->teacher?->name, 'period' => $assessment->period?->name,
                'type_label' => config("assessments.types.{$assessment->assessment_type}.label", $assessment->assessment_type),
                'result_published' => (bool) $assessment->published_at,
                'grade' => $assessment->published_at && $grade?->status === 'graded' ? (float) $grade->value : null,
                'maximum_grade' => (float) $assessment->maximum_grade,
            ];
        });
    }

    public function latestGrade(StudentAcademicEnrollment $enrollment): ?array
    {
        $assessment = Assessment::with([
            'subject:id,title',
            'grades' => fn ($query) => $query
                ->where('student_academic_enrollment_id', $enrollment->id)
                ->whereIn('status', ['graded', 'absent_with_zero']),
        ])->where('academic_year_id', $enrollment->academic_year_id)
            ->where('school_level_id', $enrollment->school_level_id)
            ->whereNotNull('published_at')
            ->whereHas('groups', fn ($query) => $query->whereKey($enrollment->school_group_id))
            ->whereHas('grades', fn ($query) => $query
                ->where('student_academic_enrollment_id', $enrollment->id)
                ->whereIn('status', ['graded', 'absent_with_zero']))
            ->latest('published_at')
            ->latest('id')
            ->first();

        if (! $assessment || ! ($grade = $assessment->grades->first())) return null;

        return [
            'id' => $assessment->id,
            'subject' => $assessment->subject?->title,
            'grade' => $grade->status === 'absent_with_zero' ? 0.0 : (float) $grade->value,
            'maximum_grade' => (float) $assessment->maximum_grade,
            'published_at' => $assessment->published_at?->toIso8601String(),
        ];
    }

    public function nextExam(StudentAcademicEnrollment $enrollment): ?array
    {
        $assessment = Assessment::with('subject:id,title')
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('school_level_id', $enrollment->school_level_id)
            ->where('assessment_type', 'exam')
            ->where('status', '!=', 'draft')
            ->whereDate('assessment_date', '>=', today())
            ->whereHas('groups', fn ($query) => $query->whereKey($enrollment->school_group_id))
            ->orderBy('assessment_date')
            ->orderBy('id')
            ->first();

        return $assessment ? [
            'id' => $assessment->id,
            'name' => $assessment->name,
            'subject' => $assessment->subject?->title,
            'date' => $assessment->assessment_date?->format('Y-m-d'),
        ] : null;
    }
}
