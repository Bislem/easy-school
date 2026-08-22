<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Course;
use App\Enums\StudentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class StudentsController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function index(Request $request): Response
    {
        $students = Student::query()
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when($request->string('student_status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->filled('course_id'), fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where(fn($enrollment)=>$enrollment
                ->whereHas('form', fn ($form) => $form->where('course_id', $request->integer('course_id')))
                ->orWhereHas('trainingPlanGroup.plan', fn($plan)=>$plan->where('course_id',$request->integer('course_id'))))))
            ->when($request->string('level')->trim()->toString(), fn ($query, string $level) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where('level', $level)))
            ->when($request->filled('group'), fn ($query) => $query->whereHas('enrollments', fn ($query) => $query->where('status', 'registered')->where('group_number', $request->integer('group'))))
            ->when($request->date('registered_from'), fn ($query, $date) => $query->whereDate('registration_date', '>=', $date))
            ->when($request->date('registered_to'), fn ($query, $date) => $query->whereDate('registration_date', '<=', $date))
            ->with(['enrollments' => fn ($query) => $query->where('status', 'registered')->with(['form.course:id,title','trainingPlanGroup.plan.course:id,title'])->latest('registered_at')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'courses' => Course::orderBy('title')->get(['id', 'title']),
            'levels' => \App\Models\CourseEnrollment::whereNotNull('level')->distinct()->orderBy('level')->pluck('level'),
            'groups' => \App\Models\CourseEnrollment::whereNotNull('group_number')->distinct()->orderBy('group_number')->pluck('group_number'),
            'studentStatuses' => collect(StudentStatus::cases())->map(fn ($status) => $status->value),
            'filters' => $request->only(['search', 'student_status', 'course_id', 'level', 'group', 'registered_from', 'registered_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateStudent($request);
        $data['registration_date'] ??= now()->toDateString();
        $data['status'] ??= $request->boolean('is_active', true) ? StudentStatus::ACTIVE : StudentStatus::STOPPED;
        if ($request->hasFile('photo')) $data['photo_path'] = $request->file('photo')->store('students', 'public');
        unset($data['photo']);
        $student = Student::create($data);
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'created', 'to_status' => $student->status->value, 'description' => 'Dossier créé manuellement.']);

        return back()->with('success', 'Étudiant ajouté avec succès.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validateStudent($request, $student);
        $requestedStatus = isset($data['status']) ? StudentStatus::from($data['status'] instanceof StudentStatus ? $data['status']->value : $data['status']) : $student->status;
        unset($data['status']);
        if ($request->hasFile('photo')) $data['photo_path'] = $request->file('photo')->store('students', 'public');
        unset($data['photo']);
        $student->fill($data);
        $changed = $student->getDirty();
        $student->save();
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'profile_updated', 'description' => 'Informations générales mises à jour.', 'metadata' => ['fields' => array_keys($changed)]]);
        if ($requestedStatus !== $student->status) $this->recordStatusChange($student, $requestedStatus, $request, 'Statut modifié pendant la mise à jour du dossier.');

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

    public function show(Student $student): Response
    {
        $student->load(['enrollments.form.course', 'enrollments.trainingPlanGroup.plan.course', 'enrollments.installments', 'enrollments.payments.recorder:id,name', 'badges.template', 'certificates.enrollment.form.course', 'histories.user:id,name', 'files', 'user:id,email,is_active', 'attendances.session.group.plan.course', 'attendances.session.teacher:id,name']);
        $expected=\App\Models\TrainingSession::whereHas('group.enrollments',fn($q)=>$q->where('student_id',$student->id)->where('status','registered'))->count();
        $records=$student->attendances;$present=$records->whereIn('status',['present','late'])->count();$consecutive=0;
        foreach($records->sortByDesc(fn($a)=>$a->session?->starts_at) as $record){if($record->status!=='absent')break;$consecutive++;}
        $rate=$expected?round($present/$expected*100,1):null;$student->setAttribute('attendance_stats',['expected'=>$expected,'recorded'=>$records->count(),'present'=>$present,'absent'=>$records->where('status','absent')->count(),'late'=>$records->where('status','late')->count(),'excused'=>$records->where('status','excused')->count(),'rate'=>$rate,'consecutive_absences'=>$consecutive,'warning'=>$consecutive>=config('attendance.consecutive_absence_warning',2)||($rate!==null&&$rate<config('attendance.warning_threshold',75))]);
        return Inertia::render('Admin/Students/Show', ['student' => $student, 'statuses' => collect(StudentStatus::cases())->map(fn ($status) => $status->value)]);
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
