<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSubject;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssessmentsController extends Controller
{
    public function index(Request $request)
    {
        $year = AcademicYear::find($request->integer('academic_year_id')) ?? AcademicYear::where('status', 'active')->latest('start_date')->first();
        $filters = $request->only(['search', 'academic_year_id', 'academic_period_id', 'school_level_id', 'school_group_id', 'subject_id', 'teacher_id', 'assessment_type', 'status']);
        $assessments = Assessment::with(['subject:id,title', 'level:id,name', 'teacher:id,name', 'groups:id,name'])
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when($filters['search'] ?? null, function ($q, $value) {
                $term = trim((string) $value);
                $q->where(function ($search) use ($term) {
                    $search->where('name', 'like', "%{$term}%")
                        ->orWhereHas('subject', fn ($subject) => $subject->where('title', 'like', "%{$term}%"))
                        ->orWhereHas('level', fn ($level) => $level->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('groups', fn ($groups) => $groups->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['academic_period_id'] ?? null, fn ($q, $value) => $q->where('academic_period_id', $value))->when($filters['school_level_id'] ?? null, fn ($q, $value) => $q->where('school_level_id', $value))->when($filters['school_group_id'] ?? null, fn ($q, $value) => $q->whereHas('groups', fn ($groups) => $groups->whereKey($value)))->when($filters['subject_id'] ?? null, fn ($q, $value) => $q->where('subject_id', $value))->when($filters['teacher_id'] ?? null, fn ($q, $value) => $q->where('teacher_id', $value))->when($filters['assessment_type'] ?? null, fn ($q, $value) => $q->where('assessment_type', $value))->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))->latest()->paginate(20)->withQueryString();
        $levels = SchoolLevel::where('is_active', true)->orderBy('sort_order')->with(['subjects' => fn ($query) => $query->wherePivot('is_active', true)->select('courses.id', 'title')])->get(['id', 'name']);

        return Inertia::render('Admin/Assessments/Index', [
            'assessments' => $assessments, 'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']), 'academicYear' => $year,
            'periods' => AcademicPeriod::orderBy('academic_year_id')->orderBy('number')->get(['id', 'name', 'number', 'academic_year_id']),
            'levels' => $levels->map(fn ($level) => ['id' => $level->id, 'name' => $level->name]),
            'subjectIdsByLevel' => $levels->mapWithKeys(fn ($level) => [$level->id => $level->subjects->pluck('id')->values()]),
            'groups' => SchoolGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'school_level_id', 'academic_year_id']),
            'subjects' => SchoolSubject::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'types' => config('assessments.types'), 'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $assessment = DB::transaction(function () use ($request) {
            $assessment = $this->persist($request);
            $this->audit($assessment, 'assessment.created', 'Assessment created');

            return $assessment;
        });

        return redirect()->route('admin.assessments.index', ['academic_year_id' => $assessment->academic_year_id])->with('success', 'Évaluation créée avec succès. Ouvrez-la pour commencer la saisie des notes.');
    }

    public function update(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->scoped($assessment);
        abort_if($assessment->status !== 'draft', 422, 'Seule une évaluation au brouillon peut être modifiée.');
        DB::transaction(function () use ($request, $assessment) {
            $old = $assessment->only(['name', 'assessment_type', 'assessment_date', 'maximum_grade', 'weight', 'teacher_id']);
            $updated = $this->persist($request, $assessment);
            $this->audit($updated, 'assessment.updated', 'Assessment configuration updated', $old, $updated->only(['name', 'assessment_type', 'assessment_date', 'maximum_grade', 'weight', 'teacher_id']));
        });

        return back()->with('success', 'Évaluation mise à jour.');
    }

    public function destroy(Assessment $assessment): RedirectResponse
    {
        $this->scoped($assessment);
        abort_unless($assessment->status === 'draft' && ! $assessment->grades()->exists(), 422, 'Seule une évaluation vide au brouillon peut être supprimée.');
        $this->audit($assessment, 'assessment.deleted', 'Assessment deleted');
        $assessment->delete();

        return back()->with('success', 'Évaluation supprimée.');
    }

    private function persist(Request $request, ?Assessment $assessment = null): Assessment
    {
        $data = $request->validate([
            'academic_year_id' => 'required|integer', 'academic_period_id' => 'required|integer', 'school_level_id' => 'required|integer', 'group_ids' => 'required|array|min:1', 'group_ids.*' => 'integer', 'subject_id' => 'required|integer', 'teacher_id' => 'nullable|integer', 'name' => 'required|string|max:160', 'assessment_type' => ['required', Rule::in(array_keys(config('assessments.types')))], 'assessment_date' => 'nullable|date', 'maximum_grade' => 'required|numeric|min:0.01|max:999.99', 'weight' => 'required|numeric|min:0.01|max:999.99', 'description' => 'nullable|string|max:5000',
        ]);
        $tenant = app(\App\Tenancy\TenantContext::class)->id();
        $year = AcademicYear::whereKey($data['academic_year_id'])->where('tenant_id', $tenant)->firstOrFail();
        AcademicPeriod::whereKey($data['academic_period_id'])->where('academic_year_id', $year->id)->firstOrFail();
        $level = SchoolLevel::findOrFail($data['school_level_id']);
        abort_unless($level->subjects()->whereKey($data['subject_id'])->exists(), 422, 'La matière n’est pas affectée à ce niveau.');
        $groups = SchoolGroup::where('academic_year_id', $year->id)->where('school_level_id', $level->id)->whereIn('id', $data['group_ids'])->get();
        abort_unless($groups->count() === count(array_unique($data['group_ids'])), 422, 'Chaque classe doit appartenir à l’année scolaire et au niveau sélectionnés.');
        if ($data['teacher_id']) {
            User::whereKey($data['teacher_id'])->where('role', UserRole::TEACHER->value)->where('is_active', true)->firstOrFail();
        }
        $payload = collect($data)->except('group_ids')->all();
        $assessment ??= new Assessment(['tenant_id' => $tenant, 'status' => 'draft']);
        $assessment->fill($payload)->save();
        $assessment->groups()->sync($groups->pluck('id')->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenant]]));

        return $assessment;
    }

    private function scoped(Assessment $assessment): void
    {
        abort_unless((int) $assessment->tenant_id === (int) app(\App\Tenancy\TenantContext::class)->id(), 404);
    }

    private function audit(Assessment $assessment, string $event, string $description, array $old = [], array $new = []): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'event' => $event, 'related_type' => Assessment::class, 'related_id' => $assessment->id, 'description' => $description, 'old_values' => $old, 'new_values' => $new, 'occurred_at' => now()]);
    }
}
