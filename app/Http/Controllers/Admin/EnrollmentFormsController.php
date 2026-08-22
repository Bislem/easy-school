<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\EnrollmentForm;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Enums\ApplicationStatus;
use App\Enums\StudentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'courses' => Course::where('is_active', true)->orderBy('title')->get(['id', 'title', 'code']),
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
        $confirmed = $enrollmentForm->enrollments()->where('status', 'registered')->count();

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
            ->with('student:id,first_name,last_name,email,phone')
            ->orderByRaw("status = 'registered' DESC")
            ->orderBy('group_number')
            ->orderBy('last_name')
            ->paginate(25);
        $groupCounts = $enrollmentForm->enrollments()->where('status', 'registered')
            ->selectRaw('group_number, COUNT(*) as total')->groupBy('group_number')->pluck('total', 'group_number');

        return Inertia::render('Admin/EnrollmentForms/Show', [
            'enrollmentForm' => $enrollmentForm,
            'enrollments' => $enrollments,
            'stats' => [
                'confirmed' => $enrollmentForm->enrollments()->where('status', 'registered')->count(),
                'pending' => $enrollmentForm->enrollments()->whereNot('status', 'registered')->count(),
                'groups' => $groupCounts,
            ],
            'applicationStatuses' => collect(ApplicationStatus::cases())->map(fn ($status) => $status->value),
        ]);
    }

    public function updateGroup(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id && $enrollment->status === ApplicationStatus::REGISTERED, 404);
        $validated = $request->validate([
            'group_number' => ['required', 'integer', 'min:1', 'max:'.$enrollmentForm->groups_count],
        ]);
        if ($enrollment->group_number !== $validated['group_number']) {
            $inGroup = $enrollmentForm->enrollments()->where('status', 'registered')
                ->where('group_number', $validated['group_number'])->count();
            abort_if($inGroup >= $enrollmentForm->groupCapacity(), 422, 'Ce groupe a atteint sa capacité maximale.');
        }
        $enrollment->update($validated);
        $this->syncPlanningGroup($enrollment, $enrollmentForm, $validated['group_number']);
        $enrollment->histories()->create(['user_id' => $request->user()->id, 'event' => 'group_changed', 'description' => 'Groupe modifié.', 'metadata' => ['group_number' => $validated['group_number']]]);

        return back()->with('success', 'Le groupe de l’étudiant a été mis à jour.');
    }

    public function addEnrollment(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'level' => ['nullable', 'string', 'max:100'],
            'group_number' => ['nullable', 'integer', 'min:1', 'max:'.$enrollmentForm->groups_count],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        if ($enrollmentForm->enrollments()->whereRaw('LOWER(email) = ?', [$validated['email']])->exists()) {
            return back()->withErrors(['email' => 'Cet étudiant est déjà inscrit à cette formation.']);
        }

        DB::transaction(function () use ($validated, $enrollmentForm, $request) {
            $form = EnrollmentForm::query()->lockForUpdate()->findOrFail($enrollmentForm->id);
            $confirmedCount = $form->enrollments()->where('status', 'registered')->count();
            if ($confirmedCount >= $form->max_students) {
                throw ValidationException::withMessages(['email' => 'Le nombre maximum d’étudiants est atteint.']);
            }

            $group = $validated['group_number'] ?: $form->nextAvailableGroup();
            if (! $group) {
                throw ValidationException::withMessages(['group_number' => 'Tous les groupes ont atteint leur capacité maximale.']);
            }
            $inGroup = $form->enrollments()->where('status', 'registered')->where('group_number', $group)->count();
            if ($inGroup >= $form->groupCapacity()) {
                throw ValidationException::withMessages(['group_number' => 'Ce groupe a atteint sa capacité maximale.']);
            }

            $student = Student::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->first();
            if (! $student) {
                $student = Student::create([
                    ...collect($validated)->except('group_number')->all(),
                    'registration_date' => now()->toDateString(), 'status' => StudentStatus::ACTIVE, 'is_active' => true,
                ]);
            }

            $created = $form->enrollments()->create([
                ...collect($validated)->except('group_number')->all(),
                'student_id' => $student->id,
                'status' => ApplicationStatus::REGISTERED,
                'confirmation_token' => (string) Str::uuid(),
                'confirmed_at' => now(),
                'approved_at' => now(), 'registered_at' => now(),
                'group_number' => $group,
            ]);
            $created->histories()->create(['user_id' => $request->user()->id, 'event' => 'registered', 'to_status' => 'registered', 'description' => 'Inscription créée manuellement par un administrateur.']);
            $this->syncPlanningGroup($created, $form, $group);
            $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'enrollment_added', 'description' => 'Nouvelle inscription ajoutée.', 'metadata' => ['enrollment_id' => $created->id]]);
        });

        return back()->with('success', 'L’étudiant a été ajouté et affecté à un groupe.');
    }

    public function removeEnrollment(EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $this->transitionApplication($enrollment, ApplicationStatus::CANCELLED, request()->user()?->id, 'Inscription annulée par un administrateur.');

        return back()->with('success', 'L’inscription a été annulée. Son historique est conservé.');
    }

    public function updateEnrollmentStatus(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $validated = $request->validate(['status' => ['required', Rule::enum(ApplicationStatus::class)], 'notes' => ['nullable', 'string', 'max:3000']]);
        $target = ApplicationStatus::from($validated['status']);
        if ($target === ApplicationStatus::REGISTERED) return back()->withErrors(['status' => 'Utilisez l’action Inscrire pour créer le dossier étudiant.']);
        $this->transitionApplication($enrollment, $target, $request->user()->id, $validated['notes'] ?? null);
        return back()->with('success', 'Statut de la demande mis à jour.');
    }

    public function registerApplicant(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        abort_unless($enrollment->status === ApplicationStatus::APPROVED, 422, 'La demande doit être approuvée avant l’inscription.');
        $validated = $request->validate(['group_number' => ['nullable', 'integer', 'min:1', 'max:'.$enrollmentForm->groups_count], 'level' => ['nullable', 'string', 'max:100']]);

        $student = DB::transaction(function () use ($enrollmentForm, $enrollment, $validated, $request) {
            $form = EnrollmentForm::query()->lockForUpdate()->findOrFail($enrollmentForm->id);
            abort_if($form->enrollments()->where('status', 'registered')->count() >= $form->max_students, 422, 'Le nombre maximum d’étudiants est atteint.');
            $group = $validated['group_number'] ?? $form->nextAvailableGroup();
            abort_if(! $group, 422, 'Tous les groupes ont atteint leur capacité maximale.');
            abort_if($form->enrollments()->where('status', 'registered')->where('group_number', $group)->count() >= $form->groupCapacity(), 422, 'Ce groupe a atteint sa capacité maximale.');
            $student = Student::whereRaw('LOWER(email) = ?', [Str::lower($enrollment->email)])->first();
            if (! $student) $student = Student::create([
                'first_name' => $enrollment->first_name, 'last_name' => $enrollment->last_name,
                'email' => Str::lower($enrollment->email), 'phone' => $enrollment->phone,
                'parent_phone' => $enrollment->parent_phone, 'birth_date' => $enrollment->birth_date,
                'registration_date' => now()->toDateString(), 'school_level' => $validated['level'] ?? $enrollment->level,
                'status' => StudentStatus::ACTIVE, 'is_active' => true,
            ]);
            $enrollment->update(['student_id' => $student->id, 'group_number' => $group, 'level' => $validated['level'] ?? $enrollment->level, 'status' => ApplicationStatus::REGISTERED, 'registered_at' => now()]);
            $this->syncPlanningGroup($enrollment, $form, $group);
            $enrollment->histories()->create(['user_id' => $request->user()->id, 'event' => 'registered', 'from_status' => 'approved', 'to_status' => 'registered', 'description' => 'Candidat converti en étudiant inscrit.']);
            $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'created_from_application', 'to_status' => $student->status->value, 'description' => 'Dossier créé depuis une demande approuvée.', 'metadata' => ['enrollment_id' => $enrollment->id]]);
            return $student;
        });
        return to_route('admin.students.show', $student)->with('success', 'Le candidat est maintenant inscrit.');
    }

    private function transitionApplication(CourseEnrollment $enrollment, ApplicationStatus $target, ?int $userId, ?string $description): void
    {
        $from = $enrollment->status;
        $timestamps = match ($target) { ApplicationStatus::CONTACTED => ['contacted_at' => now()], ApplicationStatus::APPROVED => ['approved_at' => now()], ApplicationStatus::REJECTED => ['rejected_at' => now()], ApplicationStatus::CANCELLED => ['cancelled_at' => now()], default => [] };
        $enrollment->update(['status' => $target, ...$timestamps]);
        $enrollment->histories()->create(['user_id' => $userId, 'event' => 'status_changed', 'from_status' => $from->value, 'to_status' => $target->value, 'description' => $description]);
    }

    private function syncPlanningGroup(CourseEnrollment $enrollment, EnrollmentForm $form, int $groupNumber): void
    {
        $groupId = $form->trainingPlans()->latest('id')->first()?->groups()
            ->where('group_number', $groupNumber)->value('id');
        $enrollment->update(['training_plan_group_id' => $groupId]);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->where('is_active', true)],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', UserRole::TEACHER->value)->where('is_active', true))],
            'classroom_id' => ['nullable', Rule::exists('classrooms', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'min_students' => ['required', 'integer', 'min:1'],
            'max_students' => ['required', 'integer', 'gte:min_students', 'max:100000'],
            'groups_count' => ['required', 'integer', 'min:1', 'lte:max_students'],
            'students_per_group' => ['nullable', 'required_without:classroom_id', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'cover_temp_folders' => ['array'],
            'cover_temp_folders.*' => ['string'],
            'cover_removed_files' => ['array'],
            'cover_removed_files.*' => ['integer'],
        ]);

        if ($validated['classroom_id']) {
            $validated['students_per_group'] = null;
            $classroom = Classroom::findOrFail($validated['classroom_id']);
            $availablePlaces = $classroom->capacity * $validated['groups_count'];
            if ($validated['max_students'] > $availablePlaces) {
                throw ValidationException::withMessages([
                    'max_students' => "Cette salle permet au maximum {$availablePlaces} étudiants pour {$validated['groups_count']} groupe(s).",
                ]);
            }
        } else {
            $availablePlaces = $validated['students_per_group'] * $validated['groups_count'];
            if ($validated['max_students'] > $availablePlaces) {
                throw ValidationException::withMessages([
                    'max_students' => "La capacité définie permet au maximum {$availablePlaces} étudiants pour {$validated['groups_count']} groupe(s).",
                ]);
            }
        }

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
