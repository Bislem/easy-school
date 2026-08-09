<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\EnrollmentForm;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrainingPlansController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = TrainingPlan::query()
            ->with(['course:id,title,code,duration_hours', 'teacher:id,name', 'enrollmentForm:id,title,start_date,end_date', 'groups.classroom:id,name,capacity', 'groups.sessions:id,training_plan_group_id,starts_at,ends_at'])
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query
                ->where(fn ($query) => $query->where('title', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($query) => $query->where('title', 'like', "%{$search}%"))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(12)->withQueryString();

        $plans->through(function (TrainingPlan $plan) {
            $plan->setAttribute('sessions_count', $plan->groups->sum(fn ($group) => $group->sessions->count()));
            $plan->setAttribute('planned_hours', round($plan->groups->sum(fn ($group) => $group->sessions->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at))) / 60, 1));
            return $plan;
        });

        return Inertia::render('Admin/TrainingPlans/Index', [
            'plans' => $plans,
            'courses' => Course::where('is_active', true)->orderBy('title')->get(['id', 'title', 'code', 'duration_hours']),
            'forms' => EnrollmentForm::with('course:id,title,code,duration_hours')->where('is_active', true)->orderBy('start_date')->get(['id', 'course_id', 'teacher_id', 'classroom_id', 'title', 'start_date', 'end_date', 'groups_count', 'students_per_group']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['form', 'course'])],
            'enrollment_form_id' => ['nullable', 'required_if:source_type,form', 'exists:enrollment_forms,id'],
            'course_id' => ['nullable', 'required_if:source_type,course', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'required_if:source_type,course', Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value)],
            'title' => ['required', 'string', 'max:255'],
            'groups_count' => ['nullable', 'required_if:source_type,course', 'integer', 'min:1', 'max:100'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $form = $validated['source_type'] === 'form' ? EnrollmentForm::findOrFail($validated['enrollment_form_id']) : null;
            $courseId = $form?->course_id ?? $validated['course_id'];
            $teacherId = $form?->teacher_id ?? $validated['teacher_id'];
            $groupsCount = $form?->groups_count ?? $validated['groups_count'];
            $classroomId = $form?->classroom_id ?? ($validated['classroom_id'] ?? null);
            $capacity = $form ? $form->groupCapacity() : ($classroomId ? Classroom::find($classroomId)?->capacity : null);

            $plan = TrainingPlan::create([
                'course_id' => $courseId, 'enrollment_form_id' => $form?->id,
                'teacher_id' => $teacherId, 'title' => $validated['title'],
                'status' => 'draft', 'notes' => $validated['notes'] ?? null,
            ]);
            foreach (range(1, $groupsCount) as $number) {
                $plan->groups()->create(['group_number' => $number, 'name' => "Groupe {$number}", 'classroom_id' => $classroomId, 'capacity' => $capacity]);
            }
            return $plan;
        });

        return to_route('admin.training-plans.show', $plan)->with('success', 'Planification créée. Vous pouvez maintenant programmer les séances.');
    }

    public function show(TrainingPlan $trainingPlan): Response
    {
        $trainingPlan->load(['course', 'teacher:id,name,email,phone', 'enrollmentForm', 'groups' => fn ($query) => $query->orderBy('group_number'), 'groups.classroom', 'groups.sessions' => fn ($query) => $query->with(['classroom:id,name,code', 'teacher:id,name'])->orderBy('starts_at')]);
        $trainingPlan->groups->each(function ($group) {
            $group->setAttribute('planned_hours', round($group->sessions->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at)) / 60, 1));
        });

        return Inertia::render('Admin/TrainingPlans/Show', [
            'plan' => $trainingPlan,
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
        ]);
    }

    public function update(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value)],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $trainingPlan->update($validated);
        return back()->with('success', 'Planification mise à jour.');
    }

    public function storeGroup(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $validated = $this->validateGroup($request);
        $number = ((int) $trainingPlan->groups()->max('group_number')) + 1;
        $trainingPlan->groups()->create([...$validated, 'group_number' => $number]);
        return back()->with('success', 'Groupe ajouté.');
    }

    public function updateGroup(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->ensureGroup($trainingPlan, $group);
        $group->update($this->validateGroup($request));
        return back()->with('success', 'Groupe mis à jour.');
    }

    public function destroyGroup(TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->ensureGroup($trainingPlan, $group);
        if ($trainingPlan->groups()->count() <= 1) {
            return back()->withErrors(['group' => 'Une planification doit conserver au moins un groupe.']);
        }
        $group->delete();
        return back()->with('success', 'Groupe et ses séances supprimés.');
    }

    public function storeSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->ensureGroup($trainingPlan, $group);
        $validated = $this->validateSession($request, $trainingPlan, $group);
        $this->assertNoConflict($validated);
        $this->assertDuration($trainingPlan, $group, $validated);
        $group->sessions()->create($validated);
        return back()->with('success', 'Séance planifiée avec succès.');
    }

    public function updateSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->ensureGroup($trainingPlan, $group);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        $validated = $this->validateSession($request, $trainingPlan, $group);
        $this->assertNoConflict($validated, $session);
        $this->assertDuration($trainingPlan, $group, $validated, $session);
        $session->update($validated);
        return back()->with('success', 'Séance mise à jour.');
    }

    public function destroySession(TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->ensureGroup($trainingPlan, $group);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        $session->delete();
        return back()->with('success', 'Séance supprimée.');
    }

    private function validateGroup(Request $request): array
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100'], 'classroom_id' => ['nullable', 'exists:classrooms,id'], 'capacity' => ['nullable', 'integer', 'min:1']]);
        if ($validated['classroom_id']) {
            $room = Classroom::findOrFail($validated['classroom_id']);
            if (($validated['capacity'] ?? $room->capacity) > $room->capacity) {
                throw ValidationException::withMessages(['capacity' => "La capacité ne peut pas dépasser celle de la salle ({$room->capacity})."]);
            }
            $validated['capacity'] ??= $room->capacity;
        }
        return $validated;
    }

    private function validateSession(Request $request, TrainingPlan $plan, TrainingPlanGroup $group): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'classroom_id' => ['required', 'exists:classrooms,id'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value)],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($plan->enrollmentForm && ($request->date('starts_at')->toDateString() < $plan->enrollmentForm->start_date->toDateString() || $request->date('ends_at')->toDateString() > $plan->enrollmentForm->end_date->toDateString())) {
            throw ValidationException::withMessages(['starts_at' => 'La séance doit être comprise dans les dates du formulaire d’inscription.']);
        }
        return $validated;
    }

    private function assertNoConflict(array $data, ?TrainingSession $except = null): void
    {
        $overlap = fn ($query) => $query->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->when($except, fn ($query) => $query->whereKeyNot($except->id));
        if ($overlap(TrainingSession::where('classroom_id', $data['classroom_id']))->exists()) {
            throw ValidationException::withMessages(['classroom_id' => 'Cette salle est déjà occupée pendant cet horaire.']);
        }
        if ($overlap(TrainingSession::where('teacher_id', $data['teacher_id']))->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Ce formateur anime déjà une autre séance pendant cet horaire.']);
        }
    }

    private function assertDuration(TrainingPlan $plan, TrainingPlanGroup $group, array $data, ?TrainingSession $except = null): void
    {
        $planned = $group->sessions()->when($except, fn ($query) => $query->whereKeyNot($except->id))->get()->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at));
        $newMinutes = \Illuminate\Support\Carbon::parse($data['starts_at'])->diffInMinutes(\Illuminate\Support\Carbon::parse($data['ends_at']));
        if ($planned + $newMinutes > $plan->course->duration_hours * 60) {
            throw ValidationException::withMessages(['ends_at' => "La durée totale planifiée dépasserait les {$plan->course->duration_hours} heures de la formation."]);
        }
    }

    private function ensureGroup(TrainingPlan $plan, TrainingPlanGroup $group): void
    {
        abort_unless($group->training_plan_id === $plan->id, 404);
    }
}
