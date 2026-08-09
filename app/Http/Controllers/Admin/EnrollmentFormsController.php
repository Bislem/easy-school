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
                'enrollments as confirmed_enrollments_count' => fn ($query) => $query->whereNotNull('confirmed_at'),
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
        $confirmed = $enrollmentForm->enrollments()->whereNotNull('confirmed_at')->count();

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
            ->orderByRaw('confirmed_at IS NULL')
            ->orderBy('group_number')
            ->orderBy('last_name')
            ->paginate(25);
        $groupCounts = $enrollmentForm->enrollments()->whereNotNull('confirmed_at')
            ->selectRaw('group_number, COUNT(*) as total')->groupBy('group_number')->pluck('total', 'group_number');

        return Inertia::render('Admin/EnrollmentForms/Show', [
            'enrollmentForm' => $enrollmentForm,
            'enrollments' => $enrollments,
            'stats' => [
                'confirmed' => $enrollmentForm->enrollments()->whereNotNull('confirmed_at')->count(),
                'pending' => $enrollmentForm->enrollments()->whereNull('confirmed_at')->count(),
                'groups' => $groupCounts,
            ],
        ]);
    }

    public function updateGroup(Request $request, EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id && $enrollment->confirmed_at, 404);
        $validated = $request->validate([
            'group_number' => ['required', 'integer', 'min:1', 'max:'.$enrollmentForm->groups_count],
        ]);
        if ($enrollment->group_number !== $validated['group_number']) {
            $inGroup = $enrollmentForm->enrollments()->whereNotNull('confirmed_at')
                ->where('group_number', $validated['group_number'])->count();
            abort_if($inGroup >= $enrollmentForm->groupCapacity(), 422, 'Ce groupe a atteint sa capacité maximale.');
        }
        $enrollment->update($validated);

        return back()->with('success', 'Le groupe de l’étudiant a été mis à jour.');
    }

    public function addEnrollment(Request $request, EnrollmentForm $enrollmentForm): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'group_number' => ['nullable', 'integer', 'min:1', 'max:'.$enrollmentForm->groups_count],
        ]);
        $validated['email'] = Str::lower($validated['email']);

        if ($enrollmentForm->enrollments()->whereRaw('LOWER(email) = ?', [$validated['email']])->exists()) {
            return back()->withErrors(['email' => 'Cet étudiant est déjà inscrit à cette formation.']);
        }

        DB::transaction(function () use ($validated, $enrollmentForm) {
            $form = EnrollmentForm::query()->lockForUpdate()->findOrFail($enrollmentForm->id);
            $confirmedCount = $form->enrollments()->whereNotNull('confirmed_at')->count();
            if ($confirmedCount >= $form->max_students) {
                throw ValidationException::withMessages(['email' => 'Le nombre maximum d’étudiants est atteint.']);
            }

            $group = $validated['group_number'] ?: $form->nextAvailableGroup();
            if (! $group) {
                throw ValidationException::withMessages(['group_number' => 'Tous les groupes ont atteint leur capacité maximale.']);
            }
            $inGroup = $form->enrollments()->whereNotNull('confirmed_at')->where('group_number', $group)->count();
            if ($inGroup >= $form->groupCapacity()) {
                throw ValidationException::withMessages(['group_number' => 'Ce groupe a atteint sa capacité maximale.']);
            }

            $student = Student::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->first();
            if (! $student) {
                $student = Student::create([
                    ...collect($validated)->except('group_number')->all(),
                    'is_active' => true,
                ]);
            }

            $form->enrollments()->create([
                ...collect($validated)->except('group_number')->all(),
                'student_id' => $student->id,
                'confirmation_token' => (string) Str::uuid(),
                'confirmed_at' => now(),
                'group_number' => $group,
            ]);
        });

        return back()->with('success', 'L’étudiant a été ajouté et affecté à un groupe.');
    }

    public function removeEnrollment(EnrollmentForm $enrollmentForm, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->enrollment_form_id === $enrollmentForm->id, 404);
        $enrollment->delete();

        return back()->with('success', 'L’inscription a été supprimée. Le dossier étudiant a été conservé.');
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
