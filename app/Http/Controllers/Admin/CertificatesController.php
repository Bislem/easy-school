<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateType;
use App\Enums\ManagementPermission;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CompanySetting;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLevel;
use App\Models\Student;
use App\Models\TrainingPlan;
use App\Models\User;
use App\Services\BadgeQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CertificatesController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(ManagementPermission::CERTIFICATES_VIEW->value);
        $items = Certificate::with(['student:id,first_name,last_name', 'enrollment.form.course:id,title'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('issue_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('issue_date', '<=', $request->date('date_to')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('teacher_id'), fn ($query) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment
                ->whereHas('form', fn ($form) => $form->where('teacher_id', $request->integer('teacher_id')))
                ->orWhereHas('trainingPlanGroup.plan', fn ($plan) => $plan->where('teacher_id', $request->integer('teacher_id')))))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($nested) => $nested->where('certificate_number', 'like', "%{$search}%")
                    ->orWhere('student_name', 'like', "%{$search}%")
                    ->orWhere('formation_name', 'like', "%{$search}%"));
            })->latest('issue_date')->paginate(20)->withQueryString();

        $students = Student::with(['enrollments' => fn ($query) => $query->where('status', 'registered')->with('form.course')])
            ->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'status', 'is_active']);

        return Inertia::render('Admin/Certificates/Index', [
            'certificates' => $items,
            'students' => $students,
            'types' => collect(CertificateType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
            'courses' => Course::with(['levels:id,course_id,name,duration_hours'])->orderBy('title')->get(['id', 'title', 'code', 'duration_hours', 'is_certified'])->map(fn ($course) => [...$course->toArray(), 'label' => $course->title.' · '.$course->code]),
            'teachers' => User::where('role', 'teacher')->orderBy('name')->get(['id', 'name'])->map(fn ($teacher) => [...$teacher->toArray(), 'label' => $teacher->name]),
            'completedPlans' => $this->completedPlans(),
            'filters' => $request->only(['search', 'type', 'date_from', 'date_to', 'course_id', 'student_id', 'teacher_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(ManagementPermission::CERTIFICATES_ISSUE->value);
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_enrollment_id' => ['nullable', 'exists:course_enrollments,id'],
            'course_id' => ['nullable', 'exists:courses,id'], 'course_level_id' => ['nullable', 'exists:course_levels,id'],
            'type' => ['required', Rule::enum(CertificateType::class)],
            'issue_date' => ['required', 'date'],
            'formation_start' => ['nullable', 'date'],
            'formation_end' => ['nullable', 'date', 'after_or_equal:formation_start'],
            'result' => ['nullable', 'string', 'max:255'],
            'signature_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $student = Student::findOrFail($data['student_id']);
        $enrollment = isset($data['course_enrollment_id'])
            ? CourseEnrollment::with(['student', 'form.course'])->findOrFail($data['course_enrollment_id']) : null;
        abort_if($enrollment && $enrollment->student_id !== $student->id, 422, 'Cette inscription ne correspond pas à l’étudiant.');
        $course = $enrollment?->form?->course ?? (isset($data['course_id']) ? Course::findOrFail($data['course_id']) : null);
        $level = isset($data['course_level_id']) ? CourseLevel::findOrFail($data['course_level_id']) : null;
        abort_if($level && (! $course || $level->course_id !== $course->id), 422, 'Ce niveau ne correspond pas à la formation choisie.');
        $type = CertificateType::from($data['type']);
        abort_if($course && $type === CertificateType::DIPLOMA && ! $course->is_certified, 422, 'Cette formation ne délivre pas de diplôme.');

        $certificate = $this->issue($student, $type, [...$data, 'course' => $course, 'level_record' => $level], $request->user()->id, $enrollment);
        return back()->with('success', $type->label().' '.$certificate->certificate_number.' généré(e).');
    }

    public function storeBulk(Request $request): RedirectResponse
    {
        Gate::authorize(ManagementPermission::CERTIFICATES_ISSUE->value);
        $data = $request->validate([
            'training_plan_id' => ['required', 'exists:training_plans,id'],
            'group_ids' => ['required', 'array', 'min:1'], 'group_ids.*' => ['integer', 'distinct'],
            'enrollment_ids' => ['required', 'array', 'min:1'], 'enrollment_ids.*' => ['integer', 'distinct'],
            'type' => ['required', Rule::enum(CertificateType::class)], 'issue_date' => ['required', 'date'],
            'result' => ['nullable', 'string', 'max:255'], 'signature_name' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = TrainingPlan::with(['course', 'level', 'groups.sessions'])->where('status', 'completed')->findOrFail($data['training_plan_id']);
        $groupIds = $plan->groups->whereIn('id', $data['group_ids'])->pluck('id');
        abort_unless($groupIds->count() === count($data['group_ids']), 422);
        $enrollments = CourseEnrollment::with(['student', 'form.course'])->whereIn('id', $data['enrollment_ids'])
            ->whereIn('training_plan_group_id', $groupIds)->where('status', 'registered')->whereNotNull('student_id')->get();
        abort_unless($enrollments->count() === count($data['enrollment_ids']), 422);
        $type = CertificateType::from($data['type']);
        abort_if($type === CertificateType::DIPLOMA && ! $plan->course->is_certified, 422, 'Cette formation ne délivre pas de diplôme.');
        $sessions = $plan->groups->whereIn('id', $groupIds)->flatMap->sessions;
        $base = [...$data, 'course' => $plan->course, 'level_record' => $plan->level,
            'duration_hours' => $plan->level->duration_hours, 'formation_start' => $sessions->min('starts_at')?->toDateString(),
            'formation_end' => $sessions->max('ends_at')?->toDateString()];
        $created = 0; $skipped = 0;
        DB::transaction(function () use ($enrollments, $type, $base, $request, &$created, &$skipped) {
            foreach ($enrollments as $enrollment) {
                if (Certificate::where('course_enrollment_id', $enrollment->id)->where('type', $type->value)->exists()) { $skipped++; continue; }
                $this->issue($enrollment->student, $type, $base, $request->user()->id, $enrollment); $created++;
            }
        });
        return back()->with('success', "{$created} certificat(s) généré(s). {$skipped} doublon(s) ignoré(s).");
    }

    public function print(Certificate $certificate, BadgeQrCode $qr): HttpResponse
    {
        Gate::authorize(ManagementPermission::CERTIFICATES_PRINT->value);
        $school = CompanySetting::current(); $qrCode = 'data:image/svg+xml;base64,'.base64_encode($qr->svg($certificate->verification_url, 260));
        $schoolLogo = $this->localImage($school->logo_url);
        $landscape = in_array($certificate->type, [CertificateType::SUCCESS, CertificateType::DIPLOMA], true);
        $view = $landscape ? 'admin.certificates.print-landscape' : 'admin.certificates.print-portrait';
        return Pdf::loadView($view, compact('certificate', 'school', 'qrCode', 'schoolLogo'))
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->download($certificate->certificate_number.'.pdf');
    }

    private function issue(Student $student, CertificateType $type, array $data, int $userId, ?CourseEnrollment $enrollment = null): Certificate
    {
        $attendances = $enrollment ? $student->attendances()->where('course_enrollment_id', $enrollment->id)->get() : collect();
        $attended = $attendances->whereIn('status', ['present', 'late'])->count();
        $course = $enrollment?->form?->course ?? ($data['course'] ?? null);
        $levelRecord = $data['level_record'] ?? null;
        return Certificate::create([
            'student_id' => $student->id, 'course_enrollment_id' => $enrollment?->id, 'type' => $type,
            'course_id' => $course?->id, 'course_level_id' => $levelRecord?->id,
            'certificate_number' => 'CERT-'.now()->format('Y').'-'.str_pad((string) (Certificate::max('id') + 1), 6, '0', STR_PAD_LEFT).'-'.str()->upper(str()->random(4)),
            'verification_token' => hash('sha256', str()->uuid().str()->random(32)), 'issue_date' => $data['issue_date'],
            'student_name' => $student->full_name, 'formation_name' => $course?->title,
            'level' => $enrollment?->level ?? $levelRecord?->name, 'group_label' => $enrollment?->group_number ? 'Groupe '.$enrollment->group_number : null,
            'duration_hours' => $levelRecord?->duration_hours ?? $course?->duration_hours,
            'formation_start' => $enrollment?->form?->start_date ?? ($data['formation_start'] ?? null),
            'formation_end' => $enrollment?->form?->end_date ?? ($data['formation_end'] ?? null),
            'attendance_rate' => $attendances->count() ? round($attended / $attendances->count() * 100, 2) : null,
            'result' => $data['result'] ?? null, 'signature_name' => $data['signature_name'] ?? null,
            'notes' => $data['notes'] ?? null, 'issued_by' => $userId,
        ]);
    }

    private function completedPlans(): array
    {
        $plans = TrainingPlan::with(['course', 'level:id,name,duration_hours', 'teacher:id,name',
            'groups.sessions:id,training_plan_group_id,starts_at,ends_at', 'groups.enrollments.student:id,first_name,last_name,status,is_active',
            'groups.enrollments.student.attendances:id,student_id,course_enrollment_id,status'])
            ->where('status', 'completed')->latest()->get();

        return $plans->map(function ($plan) {
            $groups = $plan->groups->map(function ($group) {
                $enrollments = $group->enrollments->filter(fn ($enrollment) => $enrollment->student)->map(function ($enrollment) {
                    $records = $enrollment->student->attendances->where('course_enrollment_id', $enrollment->id);
                    $rate = $records->count() ? round($records->whereIn('status', ['present', 'late'])->count() / $records->count() * 100, 1) : null;
                    $status = $enrollment->student->status?->value;
                    $profileValid = $enrollment->student->is_active && ! in_array($status, ['stopped', 'suspended', 'cancelled'], true);
                    $reason = ! $profileValid ? 'Profil inactif ou suspendu' : ($rate === null ? 'Présence non renseignée' : ($rate <= 0 ? 'Absent' : null));
                    return ['id' => $enrollment->id, 'student' => $enrollment->student, 'attendance_rate' => $rate, 'eligible' => $reason === null, 'warning' => $reason];
                })->values();
                return ['id' => $group->id, 'name' => $group->name, 'enrollments' => $enrollments];
            })->values();
            return ['id' => $plan->id, 'title' => $plan->title, 'course' => $plan->course, 'level' => $plan->level, 'teacher' => $plan->teacher, 'groups' => $groups, 'label' => $plan->title.' · '.$plan->course->title.' · '.$plan->teacher->name];
        })->values()->all();
    }

    private function localImage(?string $url): ?string
    {
        if (! $url) return null; $path = parse_url($url, PHP_URL_PATH);
        if (! str_starts_with((string) $path, '/storage/')) return null; $file = public_path(ltrim($path, '/'));
        if (! is_file($file)) return null;
        return 'data:'.(mime_content_type($file) ?: 'image/png').';base64,'.base64_encode(file_get_contents($file));
    }
}
