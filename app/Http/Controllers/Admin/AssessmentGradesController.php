<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Assessment, AuditLog, Grade, StudentAcademicEnrollment};
use App\Services\AssessmentLifecycleService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AssessmentGradesController extends Controller
{
    public function index(Assessment $assessment)
    {
        $assessment = $this->scoped($assessment);
        $students = StudentAcademicEnrollment::with('student:id,first_name,last_name,registration_number')->where('tenant_id', $assessment->tenant_id)->where('academic_year_id', $assessment->academic_year_id)->where('school_level_id', $assessment->school_level_id)->whereIn('school_group_id', $assessment->groups->pluck('id'))->get()->sortBy(fn ($enrollment) => $enrollment->student?->registration_number ?? $enrollment->student?->last_name)->values();
        $grades = Grade::where('assessment_id', $assessment->id)->whereIn('student_academic_enrollment_id', $students->pluck('id'))->get()->keyBy('student_academic_enrollment_id');
        $history = AuditLog::with('user:id,name')
            ->where(function ($query) use ($assessment) {
                $query->where(function ($assessmentEvents) use ($assessment) {
                    $assessmentEvents->where('related_type', Assessment::class)
                        ->where('related_id', $assessment->id);
                })->orWhere(function ($gradeEvents) use ($assessment) {
                    $gradeEvents->where('related_type', Grade::class)
                        ->whereIn('related_id', $assessment->grades()->select('id'));
                });
            })
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        return Inertia::render('Admin/Assessments/Grades', ['assessment'=>$assessment, 'students'=>$students->map(fn ($enrollment) => ['enrollment_id'=>$enrollment->id, 'first_name'=>$enrollment->student?->first_name, 'last_name'=>$enrollment->student?->last_name, 'registration_number'=>$enrollment->student?->registration_number, 'grade'=>($grade=$grades->get($enrollment->id))?->value, 'status'=>$grade?->status ?? 'not_graded', 'comment'=>$grade?->comment])->values(), 'history'=>$history]);
    }

    public function save(Request $request, Assessment $assessment, AssessmentLifecycleService $lifecycle): RedirectResponse
    {
        $assessment = $this->scoped($assessment);
        abort_if($assessment->status === 'locked', 422, 'Cette évaluation est verrouillée. Rouvrez-la avant de corriger les notes.');
        abort_unless(in_array($assessment->status, ['open', 'completed'], true), 422, 'Ouvrez cette évaluation avant de saisir les notes.');
        $data = $request->validate([
            'grades' => 'required|array',
            'grades.*.enrollment_id' => 'required|integer|distinct',
            'grades.*.value' => 'nullable|numeric',
            'grades.*.status' => 'required|in:graded,absent,exempted,not_graded,absent_with_zero',
            'grades.*.comment' => 'nullable|string|max:1000',
            'correction_reason' => 'nullable|string|min:10|max:2000',
        ]);
        if ($assessment->status === 'completed' && blank($data['correction_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'correction_reason' => 'Un motif de correction d’au moins 10 caractères est obligatoire après la clôture de l’évaluation.',
            ]);
        }
        $eligible = StudentAcademicEnrollment::where('tenant_id', $assessment->tenant_id)->where('academic_year_id', $assessment->academic_year_id)->where('school_level_id', $assessment->school_level_id)->whereIn('school_group_id', $assessment->groups->pluck('id'))->get()->keyBy('id');
        $errors = [];
        foreach ($data['grades'] as $index => $row) {
            if (! $eligible->has((int) $row['enrollment_id'])) $errors["grades.$index.enrollment_id"] = 'Cet élève n’est pas inscrit dans une classe concernée par cette évaluation.';
            if ($row['status'] === 'graded' && ($row['value'] === null || (float) $row['value'] < 0 || (float) $row['value'] > (float) $assessment->maximum_grade)) $errors["grades.$index.value"] = "Saisissez une note comprise entre 0 et {$assessment->maximum_grade}.";
        }
        if ($errors) throw ValidationException::withMessages($errors);

        $saved = DB::transaction(function () use ($data, $eligible, $assessment, $lifecycle) {
            $saved = 0;
            foreach ($data['grades'] as $row) {
                $enrollment = $eligible->get((int) $row['enrollment_id']);
                $existing = Grade::where('assessment_id', $assessment->id)->where('student_academic_enrollment_id', $enrollment->id)->first();
                $new = ['tenant_id'=>$assessment->tenant_id, 'student_id'=>$enrollment->student_id, 'value'=>$row['status'] === 'graded' ? $row['value'] : null, 'status'=>$row['status'], 'comment'=>$row['comment'] ?? null, 'entered_by'=>auth()->id()];
                $old = $existing?->only(['value','status','comment']);
                $grade = Grade::updateOrCreate(['assessment_id'=>$assessment->id, 'student_academic_enrollment_id'=>$enrollment->id], $new);
                if ($old !== $grade->only(['value','status','comment'])) $lifecycle->auditGrade($grade->load('assessment'), auth()->id(), $old, $data['correction_reason'] ?? null);
                $saved++;
            }
            return $saved;
        });
        return back()->with('success', "$saved note(s) enregistrée(s).");
    }

    private function scoped(Assessment $assessment): Assessment
    {
        abort_unless((int) $assessment->tenant_id === (int) app(\App\Tenancy\TenantContext::class)->id(), 404);
        return $assessment->load(['year','period','level','subject','teacher','groups']);
    }
}
