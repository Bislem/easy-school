<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Formation;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use App\Services\FormationEnrollmentService;
use App\Tenancy\TenantRule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class EnrollmentFormsController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function index(Request $request): Response
    {
        $forms = EnrollmentForm::query()
            ->with(['course:id,title,code', 'teacher:id,name', 'classroom:id,name,code,capacity', 'files'])
            ->withCount([
                'enrollments',
                'enrollments as confirmed_enrollments_count' => fn ($query) => $query->where('status', 'registered'),
            ])
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($query) => $query->where('title', 'like', "%{$search}%")));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/EnrollmentForms/Index', [
            'forms' => $forms,
            'courses' => Formation::where('is_active', true)->orderBy('title')->get(['id', 'title', 'code']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['cover_temp_folders' => ['required', 'array', 'min:1']]);
        $enrollmentForm = EnrollmentForm::create($this->validated($request));
        $this->syncCover($request, $enrollmentForm);

        return back()->with('success', "Formulaire d'inscription créé avec succès.");
    }

    public function update(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $coverIds = $enrollmentForm->files()->where('collection', 'cover')->pluck('id');
        $keptCovers = $coverIds->diff($request->input('cover_removed_files', []));
        if ($keptCovers->isEmpty() && $request->input('cover_temp_folders', []) === []) {
            $request->validate(['cover_temp_folders' => ['required', 'array', 'min:1']]);
        }
        $validated = $this->validated($request);
        $confirmed = $enrollmentForm->registeredCount();

        if ($validated['max_students'] < $confirmed) {
            throw ValidationException::withMessages([
                'max_students' => "Le maximum ne peut pas être inférieur au nombre d'inscriptions confirmées ({$confirmed}).",
            ]);
        }

        $enrollmentForm->update($validated);
        $this->syncCover($request, $enrollmentForm);

        return back()->with('success', "Formulaire d'inscription mis à jour.");
    }

    public function toggleActive(EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $enrollmentForm->update(['is_active' => ! $enrollmentForm->is_active]);

        return back()->with('success', $enrollmentForm->is_active
            ? 'Les inscriptions ont été ouvertes.'
            : 'Les inscriptions ont été fermées.');
    }

    public function show(EnrollmentForm $enrollmentForm): Response
    {
        $enrollmentForm->load(['course', 'teacher:id,name,email,phone', 'classroom', 'files']);
        $enrollments = $enrollmentForm->enrollments()
            ->with(['student:id,first_name,last_name,email,phone,birth_date', 'trainingPlanGroup:id,name,training_plan_id', 'trainingPlanGroup.plan:id,title'])
            ->orderByRaw("status = 'registered' DESC")
            ->orderBy('last_name')
            ->paginate(25);
        $groups = TrainingPlanGroup::with('plan:id,title,status')->withCount('enrollments')
            ->whereHas('plan', fn ($plan) => $plan->where('enrollment_form_id', $enrollmentForm->id)->whereNotIn('status', ['completed', 'cancelled']))
            ->orderBy('training_plan_id')->orderBy('group_number')->get(['id', 'training_plan_id', 'name', 'capacity']);

        return Inertia::render('Admin/EnrollmentForms/Show', [
            'enrollmentForm' => $enrollmentForm,
            'enrollments' => $enrollments,
            'stats' => [
                'confirmed' => $enrollmentForm->registeredCount(),
                'pending' => $enrollmentForm->enrollments()->whereIn('status', ['new', 'contacted', 'waiting', 'approved'])->count(),
                'unplaced' => $enrollmentForm->enrollments()->where('status', 'registered')->whereNull('training_plan_group_id')->count(),
            ],
            'groups' => $groups,
            'students' => Student::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'birth_date', 'phone']),
            'applicationStatuses' => collect(ApplicationStatus::applicationStages())->map(fn ($status) => $status->value),
        ]);
    }

    public function updateGroup(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment, FormationEnrollmentService $enrollments): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $validated = $request->validate(['training_plan_group_id' => ['nullable', TenantRule::exists('training_plan_groups')]]);
        $enrollments->place($enrollment, isset($validated['training_plan_group_id']) ? TrainingPlanGroup::find($validated['training_plan_group_id']) : null, $request->user()->id);

        return back()->with('success', 'Le groupe de l’étudiant a été mis à jour.');
    }

    /** Register a student directly: an existing student file is chosen explicitly, otherwise a new one is created. */
    public function addEnrollment(Request $request, EnrollmentForm $enrollmentForm, FormationEnrollmentService $enrollments): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', TenantRule::exists('students')],
            'first_name' => ['required_without:student_id', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_without:student_id', 'nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required_without:student_id', 'nullable', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'level' => ['nullable', 'string', 'max:100'],
            'training_plan_group_id' => ['nullable', TenantRule::exists('training_plan_groups')],
        ]);
        DB::transaction(function () use ($validated, $enrollmentForm, $request, $enrollments) {
            $student = isset($validated['student_id']) ? Student::findOrFail($validated['student_id']) : $enrollments->createStudent($validated);
            $enrollment = $enrollmentForm->enrollments()->make(['level' => $validated['level'] ?? $student->school_level]);
            $group = isset($validated['training_plan_group_id']) ? TrainingPlanGroup::find($validated['training_plan_group_id']) : null;
            $enrollments->register($enrollment, $student, $group, $request->user()->id, 'Inscription créée manuellement par un administrateur.');
            $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'enrollment_added', 'description' => 'Nouvelle inscription ajoutée.', 'metadata' => ['enrollment_id' => $enrollment->id]]);
        });

        return back()->with('success', 'L’étudiant a été inscrit.');
    }

    public function removeEnrollment(EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment, FormationEnrollmentService $enrollments): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $enrollments->transition($enrollment, ApplicationStatus::CANCELLED, request()->user()?->id, 'Inscription annulée par un administrateur.');

        return back()->with('success', 'L’inscription a été annulée. Son historique est conservé.');
    }

    public function updateEnrollmentStatus(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment, FormationEnrollmentService $enrollments): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $validated = $request->validate(['status' => ['required', Rule::in(collect(ApplicationStatus::applicationStages())->map->value)], 'notes' => ['nullable', 'string', 'max:3000']]);
        $enrollments->transition($enrollment, ApplicationStatus::from($validated['status']), $request->user()->id, $validated['notes'] ?? null);

        return back()->with('success', 'Statut de la demande mis à jour.');
    }

    /** Convert an approved application into a registration, linking it to a chosen student file or a new one. */
    public function registerApplicant(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment, FormationEnrollmentService $enrollments): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        abort_unless($enrollment->status === ApplicationStatus::APPROVED, 422, 'La demande doit être approuvée avant l’inscription.');
        $validated = $request->validate(['student_id' => ['nullable', TenantRule::exists('students')], 'training_plan_group_id' => ['nullable', TenantRule::exists('training_plan_groups')], 'level' => ['nullable', 'string', 'max:100']]);

        $student = DB::transaction(function () use ($enrollment, $validated, $request, $enrollments) {
            $student = isset($validated['student_id'])
                ? Student::findOrFail($validated['student_id'])
                : $enrollments->createStudent([...$enrollment->only(['first_name', 'last_name', 'email', 'phone', 'parent_phone', 'birth_date']), 'level' => $validated['level'] ?? $enrollment->level]);
            $enrollment->level = $validated['level'] ?? $enrollment->level;
            $group = isset($validated['training_plan_group_id']) ? TrainingPlanGroup::find($validated['training_plan_group_id']) : null;
            $enrollments->register($enrollment, $student, $group, $request->user()->id, 'Candidat converti en étudiant inscrit.');
            $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'created_from_application', 'to_status' => $student->status->value, 'description' => 'Inscription depuis une demande approuvée.', 'metadata' => ['enrollment_id' => $enrollment->id]]);

            return $student;
        });

        return to_route('admin.students.show', $student)->with('success', 'Le candidat est maintenant inscrit.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'course_id' => ['required', TenantRule::exists('courses')->where('is_active', true)->where('entity_type', 'formation')],
            'teacher_id' => ['nullable', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)->where('is_active', true)],
            'classroom_id' => ['nullable', TenantRule::exists('classrooms')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'min_students' => ['required', 'integer', 'min:1'],
            'max_students' => ['required', 'integer', 'gte:min_students', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'cover_temp_folders' => ['array'],
            'cover_temp_folders.*' => ['string'],
            'cover_removed_files' => ['array'],
            'cover_removed_files.*' => ['integer'],
        ]);

        return collect($validated)->except(['cover_temp_folders', 'cover_removed_files'])->all();
    }

    private function syncCover(Request $request, EnrollmentForm $enrollmentForm): void
    {
        $tempFolders = $request->input('cover_temp_folders', []);
        $removedIds = $request->input('cover_removed_files', []);
        if ($tempFolders !== []) {
            $removedIds = array_values(array_unique(array_merge(
                $removedIds,
                $enrollmentForm->files()->where('collection', 'cover')->pluck('id')->all(),
            )));
        }

        $this->filePondService->handleFileUpdates($enrollmentForm, $tempFolders, $removedIds, 'cover');
    }
}
