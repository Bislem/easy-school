<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Formation;
use App\Models\Student;
use App\Services\AuthorizationService;
use App\Services\StudentAcademicEnrollmentService;
use App\Services\TenantStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class StudentsController extends Controller
{
    public function __construct(private FilePondService $filePondService, private TenantStorageService $tenantStorage, private AuthorizationService $authorization) {}

    public function index(Request $request): Response
    {
        $students = $this->authorization->apply(Student::query(), $request->user(), 'students.view')
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $search = preg_replace('/\s+/', ' ', trim($search));
                $tokens = collect(preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY))
                    ->filter(fn (string $token) => mb_strlen($token) >= 1)
                    ->values();

                $query->where(function ($query) use ($search, $tokens) {
                    if (ctype_digit($search)) {
                        $query->orWhere('id', (int) $search);
                    }

                    $query->orWhere(function ($match) use ($tokens) {
                        foreach ($tokens as $token) {
                            $like = "%{$token}%";
                            $match->where(function ($fields) use ($like) {
                                $fields->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like)
                                    ->orWhere('email', 'like', $like)
                                    ->orWhere('phone', 'like', $like)
                                    ->orWhere('parent_phone', 'like', $like)
                                    ->orWhere('address', 'like', $like)
                                    ->orWhere('notes', 'like', $like)
                                    ->orWhereHas('parents', fn ($parent) => $parent
                                        ->where('first_name', 'like', $like)
                                        ->orWhere('last_name', 'like', $like)
                                        ->orWhere('phone', 'like', $like));
                            });
                        }
                    });

                    $query->orWhereHas('enrollments', function ($enrollment) use ($tokens) {
                        $enrollment->where(function ($match) use ($tokens) {
                            foreach ($tokens as $token) {
                                $like = "%{$token}%";
                                $match->where(function ($fields) use ($like) {
                                    $fields->where('email', 'like', $like)
                                        ->orWhere('phone', 'like', $like)
                                        ->orWhere('parent_phone', 'like', $like)
                                        ->orWhere('level', 'like', $like)
                                        ->orWhere('group_number', 'like', $like)
                                        ->orWhereHas('form.course', fn ($course) => $course->where('title', 'like', $like))
                                        ->orWhereHas('trainingPlanGroup.plan.course', fn ($course) => $course->where('title', 'like', $like));
                                });
                            }
                        });
                    });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when($request->string('student_status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->filled('course_id'), fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where(fn ($enrollment) => $enrollment
                ->whereHas('form', fn ($form) => $form->where('course_id', $request->integer('course_id')))
                ->orWhereHas('trainingPlanGroup.plan.level', fn ($level) => $level->where('course_id', $request->integer('course_id'))))))
            ->when($request->string('level')->trim()->toString(), fn ($query, string $level) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where('level', $level)))
            ->when($request->filled('group'), fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where('group_number', $request->integer('group'))))
            ->when($request->date('registered_from'), fn ($query, $date) => $query->whereDate('registration_date', '>=', $date))
            ->when($request->date('registered_to'), fn ($query, $date) => $query->whereDate('registration_date', '<=', $date))
            ->with(['enrollments' => fn ($query) => $query->where('status', 'registered')->with(['form.course:id,title', 'trainingPlanGroup.plan.level.course:id,title'])->latest('registered_at')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'courses' => Formation::orderBy('title')->get(['id', 'title']),
            'levels' => \App\Models\CourseEnrollment::whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'groups' => \App\Models\CourseEnrollment::whereNotNull('group_number')->distinct()->orderBy('group_number')->pluck('group_number'),
            'studentStatuses' => collect(StudentStatus::cases())->map(fn ($status) => $status->value),
            'filters' => $request->only(['search', 'status', 'student_status', 'course_id', 'level', 'group', 'registered_from', 'registered_to']),
            'isPrivateSchool' => $request->user()->tenant?->organization_type === 'private_school',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStudent($request);
        $data['registration_date'] ??= now()->toDateString();
        $data['status'] ??= $request->boolean('is_active', true) ? StudentStatus::ACTIVE : StudentStatus::STOPPED;
        unset($data['photo']);
        $student = Student::create($data);
        if ($request->hasFile('photo')) {
            $student->update(['photo_path' => $this->tenantStorage->store($request->file('photo'), 'students', 'public', TenantStorageService::PROFILE_IMAGES, 'students', $student)]);
        }
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'created', 'to_status' => $student->status->value, 'description' => 'Dossier créé manuellement.']);

        return back()->with('success', 'Étudiant ajouté avec succès.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validateStudent($request, $student);
        $requestedStatus = isset($data['status']) ? StudentStatus::from($data['status'] instanceof StudentStatus ? $data['status']->value : $data['status']) : $student->status;
        unset($data['status']);
        if ($request->hasFile('photo')) {
            $oldPhoto = $student->photo_path;
            $data['photo_path'] = $this->tenantStorage->store($request->file('photo'), 'students', 'public', TenantStorageService::PROFILE_IMAGES, 'students', $student);
            if ($oldPhoto) {
                $this->tenantStorage->delete($oldPhoto);
            }
        }
        unset($data['photo']);
        $student->fill($data);
        $changed = $student->getDirty();
        $student->save();
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'profile_updated', 'description' => 'Informations générales mises à jour.', 'metadata' => ['fields' => array_keys($changed)]]);
        if ($requestedStatus !== $student->status) {
            $this->recordStatusChange($student, $requestedStatus, $request, 'Statut modifié pendant la mise à jour du dossier.');
        }

        return back()->with('success', 'Étudiant mis à jour avec succès.');
    }

    public function toggleActive(Student $student): RedirectResponse
    {
        $from = $student->status;
        $to = $student->is_active ? StudentStatus::STOPPED : StudentStatus::ACTIVE;
        $student->update(['is_active' => ! $student->is_active, 'status' => $to]);
        $student->histories()->create(['user_id' => request()->user()?->id, 'event' => 'status_changed', 'from_status' => $from->value, 'to_status' => $to->value, 'description' => 'Activation modifiée depuis la liste.']);

        return back()->with('success', $student->is_active
            ? "L'étudiant a été activé."
            : "L'étudiant a été désactivé.");
    }

    public function show(Request $request, Student $student, StudentAcademicEnrollmentService $academicEnrollments): Response
    {
        $student->load(['enrollments.form.course', 'enrollments.trainingPlanGroup.plan.level.course', 'enrollments.installments', 'enrollments.payments.recorder:id,name', 'badges.template', 'certificates.enrollment.form.course', 'histories.user:id,name', 'files', 'user:id,email,is_active', 'observations' => fn ($query) => $query->whereNull('parent_id')->with(['author:id,name,role', 'replies.author:id,name,role']), 'attendances.session.group.plan.level.course', 'attendances.session.teacher:id,name']);
        $isPrivateSchool = $request->user()->tenant?->organization_type === 'private_school';
        $journey = $isPrivateSchool ? $academicEnrollments->history($student) : collect();
        $selectedYearId = $request->integer('academic_year_id') ?: $request->session()->get('academic_year_id');
        $selectedAcademicEnrollment = $isPrivateSchool
            ? ($journey->firstWhere('academic_year_id', (int) $selectedYearId) ?? $journey->first())
            : null;
        $expected = \App\Models\TrainingSession::whereHas('group.enrollments', fn ($q) => $q->where('student_id', $student->id)->where('status', 'registered'))->count();
        $records = $student->attendances;
        $present = $records->whereIn('status', ['present', 'late'])->count();
        $consecutive = 0;
        foreach ($records->sortByDesc(fn ($a) => $a->session?->starts_at) as $record) {
            if ($record->status !== 'absent') {
                break;
            }$consecutive++;
        }
        $rate = $expected ? round($present / $expected * 100, 1) : null;
        $student->setAttribute('attendance_stats', ['expected' => $expected, 'recorded' => $records->count(), 'present' => $present, 'absent' => $records->where('status', 'absent')->count(), 'late' => $records->where('status', 'late')->count(), 'excused' => $records->where('status', 'excused')->count(), 'rate' => $rate, 'consecutive_absences' => $consecutive, 'warning' => $consecutive >= config('attendance.consecutive_absence_warning', 2) || ($rate !== null && $rate < config('attendance.warning_threshold', 75))]);

        return Inertia::render('Admin/Students/Show', ['student' => $student, 'statuses' => collect(StudentStatus::cases())->map(fn ($status) => $status->value),
            'isPrivateSchool' => $isPrivateSchool, 'academicJourney' => $journey, 'selectedAcademicEnrollment' => $selectedAcademicEnrollment,
            'academicallyActive' => $selectedAcademicEnrollment?->isAcademicallyActive() ?? false]);
    }

    public function academicHistory(Request $request, Student $student, StudentAcademicEnrollmentService $academicEnrollments): JsonResponse
    {
        abort_unless($request->user()->tenant?->organization_type === 'private_school', 404);

        return response()->json(['data' => $academicEnrollments->history($student)]);
    }

    public function academicContext(Request $request, Student $student, AcademicYear $academicYear, StudentAcademicEnrollmentService $academicEnrollments): JsonResponse
    {
        abort_unless($request->user()->tenant?->organization_type === 'private_school', 404);
        $context = $academicEnrollments->context($student, $academicYear);

        return response()->json(['data' => $context, 'academically_active' => $context?->isAcademicallyActive() ?? false]);
    }

    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(StudentStatus::class)], 'observation' => ['nullable', 'string', 'max:3000']]);
        $to = StudentStatus::from($validated['status']);
        $from = $student->status;
        if ($from !== $to) {
            $this->recordStatusChange($student, $to, $request, $validated['observation'] ?? null);
        }

        return back()->with('success', 'Statut étudiant mis à jour.');
    }

    public function updateDocuments(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'document_temp_folders' => ['array'], 'document_temp_folders.*' => ['string'],
            'document_removed_files' => ['array'], 'document_removed_files.*' => ['integer'],
        ]);
        $this->filePondService->handleFileUpdates($student, $validated['document_temp_folders'] ?? [], $validated['document_removed_files'] ?? [], 'documents');
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'documents_updated', 'description' => 'Documents du dossier mis à jour.']);

        return back()->with('success', 'Documents mis à jour.');
    }

    public function updateMedical(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'blood_type' => ['nullable', 'string', 'max:10'], 'allergies' => ['nullable', 'string', 'max:5000'],
            'chronic_conditions' => ['nullable', 'string', 'max:5000'], 'medications' => ['nullable', 'string', 'max:5000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'], 'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'], 'medical_temp_folders' => ['array', 'max:10'],
            'medical_temp_folders.*' => ['string'], 'medical_removed_files' => ['array'], 'medical_removed_files.*' => ['integer'],
        ]);
        $student->update(collect($data)->only(['blood_type', 'allergies', 'chronic_conditions', 'medications', 'medical_notes', 'emergency_contact_name', 'emergency_contact_phone'])->all());
        $removed = $student->files()->where('collection', 'medical_documents')->whereIn('id', $data['medical_removed_files'] ?? [])->pluck('id')->all();
        $this->filePondService->handleFileUpdates($student, $data['medical_temp_folders'] ?? [], $removed, 'medical_documents');
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'medical_folder_updated', 'description' => 'Dossier médical mis à jour.']);

        return back()->with('success', 'Dossier médical mis à jour.');
    }

    private function recordStatusChange(Student $student, StudentStatus $to, Request $request, ?string $description): void
    {
        $from = $student->status;
        $student->update(['status' => $to, 'is_active' => in_array($to, [StudentStatus::ACTIVE, StudentStatus::ENROLLED], true)]);
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'status_changed', 'from_status' => $from->value, 'to_status' => $to->value, 'description' => $description]);
    }

    private function validateStudent(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('students')->ignore($student)],
            'phone' => ['required', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'registration_date' => ['nullable', 'date'], 'school_level' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(StudentStatus::class)],
            'photo' => ['nullable', 'image', 'max:5120'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
