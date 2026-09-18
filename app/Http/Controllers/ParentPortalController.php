<?php

namespace App\Http\Controllers;

use App\Enums\AcademicYearStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Models\AttendanceException;
use App\Models\MobileMembership;
use App\Models\PortalNotification;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentObservation;
use App\Models\StudentPayment;
use App\Models\TimetableSession;
use App\Models\TimetableSetting;
use App\Models\TrainingSession;
use App\Services\ParentAcademicResultsService;
use App\Services\StudentAcademicEnrollmentService;
use App\Services\TenantStorageService;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ParentPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        [$children, $child, $year] = $this->context($request);

        return Inertia::render('Parent/Dashboard', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'today' => $child && $year ? $this->today($child, $year) : null,
            'quickAccess' => $child && $year ? $this->quickAccess($child, $year) : null,
            'recentActivity' => $child ? $this->activity($request, $child, $year) : [],
        ]);
    }

    public function children(Request $request): Response
    {
        [$children, $child, $year] = $this->context($request);

        return Inertia::render('Parent/Children/Index', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'children' => $children->map(fn (Student $student) => $this->childDataInOwnSchool($student)),
        ]);
    }

    public function child(Request $request, int $student, StudentAcademicEnrollmentService $academicEnrollments): Response
    {
        $student = $this->parentChildren($request)->firstWhere('id', $student);
        abort_unless($student, 403, 'Cet enfant n’est pas associé à votre compte.');
        $this->activateChildTenant($request, $student);
        [$children, , $year] = $this->context($request, $student);
        $student->load(['enrollments.form.course', 'enrollments.trainingPlanGroup.plan.level.course', 'enrollments.installments', 'enrollments.payments.recorder:id,name', 'badges.template', 'certificates.enrollment.form.course', 'histories.user:id,name', 'files', 'user:id,email,is_active', 'observations' => fn ($query) => $query->whereNull('parent_id')->with(['author:id,name,role', 'replies.author:id,name,role']), 'attendances.session.group.plan.level.course', 'attendances.session.teacher:id,name']);
        $expected = TrainingSession::whereHas('group.enrollments', fn ($query) => $query->where('student_id', $student->id)->where('status', 'registered'))->count();
        $records = $student->attendances;
        $present = $records->whereIn('status', ['present', 'late'])->count();
        $student->setAttribute('attendance_stats', ['expected' => $expected, 'recorded' => $records->count(), 'present' => $present, 'absent' => $records->where('status', 'absent')->count(), 'late' => $records->where('status', 'late')->count(), 'excused' => $records->where('status', 'excused')->count(), 'rate' => $expected ? round($present / $expected * 100, 1) : null, 'consecutive_absences' => 0, 'warning' => false]);
        $journey = $academicEnrollments->history($student);

        return Inertia::render('Admin/Students/Show', [
            'student' => $student, 'statuses' => collect(\App\Enums\StudentStatus::cases())->map(fn ($status) => $status->value),
            'readOnly' => false, 'parentView' => true, 'isPrivateSchool' => true, 'academicJourney' => $journey,
            'selectedAcademicEnrollment' => $journey->first(), 'academicallyActive' => $journey->first()?->isAcademicallyActive() ?? false,
        ]);
    }

    public function updateChildDetails(Request $request, int $student): RedirectResponse
    {
        $student = $this->parentChildren($request)->firstWhere('id', $student);
        abort_unless($student, 403, 'Cet enfant n’est pas associé à votre compte.');
        $this->activateChildTenant($request, $student);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'registration_date' => ['nullable', 'date'],
            'school_level' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
        $student = Student::findOrFail($student->id);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = app(TenantStorageService::class)->store($request->file('photo'), 'students', 'public', TenantStorageService::PROFILE_IMAGES, 'students', $student);
        }
        unset($data['photo']);
        $student->update(collect($data)->map(fn ($value) => is_string($value) ? trim($value) ?: null : $value)->all());
        $student->histories()->create([
            'user_id' => $request->user()->id,
            'event' => 'parent_details_updated',
            'description' => 'Coordonnées du dossier mises à jour par le parent.',
        ]);

        return back()->with('success', 'Informations de l’enfant mises à jour.');
    }

    public function updateChildDocuments(Request $request, int $student, FilePondService $filePondService): RedirectResponse
    {
        $student = $this->parentChildren($request)->firstWhere('id', $student);
        abort_unless($student, 403, 'Cet enfant n’est pas associé à votre compte.');
        $this->activateChildTenant($request, $student);

        $data = $request->validate([
            'document_temp_folders' => ['array', 'max:10'],
            'document_temp_folders.*' => ['string'],
            'document_removed_files' => ['array'],
            'document_removed_files.*' => ['integer'],
        ]);
        $folders = $data['document_temp_folders'] ?? [];
        $tempFiles = TempFile::whereIn('folder', $folders)->get()->keyBy('folder');
        abort_unless($tempFiles->count() === count(array_unique($folders)), 422, 'Un ou plusieurs documents sont introuvables.');
        foreach ($folders as $folder) {
            $temp = $tempFiles->get($folder);
            abort_unless(in_array($temp->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) && $temp->size <= 10 * 1024 * 1024, 422, 'Document invalide. Formats acceptés : PDF, JPG, PNG ou WebP (10 Mo maximum).');
        }

        $student->files()->where('collection', 'documents')
            ->whereIn('id', $data['document_removed_files'] ?? [])->get()->each->delete();
        $order = $student->files()->where('collection', 'documents')->count();
        foreach ($folders as $index => $folder) {
            $filePondService->moveTempFileToModel($student, $folder, 'documents', $order + $index);
        }
        $student->histories()->create([
            'user_id' => $request->user()->id,
            'event' => 'parent_documents_updated',
            'description' => 'Documents du dossier mis à jour par le parent.',
        ]);

        return back()->with('success', 'Documents de l’enfant mis à jour.');
    }

    public function updateChildMedical(Request $request, int $student, FilePondService $filePondService): RedirectResponse
    {
        $student = $this->parentChildren($request)->firstWhere('id', $student);
        abort_unless($student, 403, 'Cet enfant n’est pas associé à votre compte.');
        $this->activateChildTenant($request, $student);
        $student = Student::findOrFail($student->id);
        $data = $request->validate($this->medicalRules());
        $student->update(collect($data)->only(['blood_type', 'allergies', 'chronic_conditions', 'medications', 'medical_notes', 'emergency_contact_name', 'emergency_contact_phone'])->map(fn ($value) => is_string($value) ? trim($value) ?: null : $value)->all());
        $student->files()->where('collection', 'medical_documents')->whereIn('id', $data['medical_removed_files'] ?? [])->get()->each->delete();
        foreach ($data['medical_temp_folders'] ?? [] as $index => $folder) {
            $filePondService->moveTempFileToModel($student, $folder, 'medical_documents', $index);
        }
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'medical_folder_updated', 'description' => 'Dossier médical mis à jour par le parent.']);

        return back()->with('success', 'Dossier médical mis à jour.');
    }

    private function medicalRules(): array
    {
        return [
            'blood_type' => ['nullable', 'string', 'max:10'], 'allergies' => ['nullable', 'string', 'max:5000'],
            'chronic_conditions' => ['nullable', 'string', 'max:5000'], 'medications' => ['nullable', 'string', 'max:5000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'], 'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'], 'medical_temp_folders' => ['array', 'max:10'],
            'medical_temp_folders.*' => ['string'], 'medical_removed_files' => ['array'], 'medical_removed_files.*' => ['integer'],
        ];
    }

    public function timetable(Request $request): Response
    {
        [$children, $child, $activeYear] = $this->context($request);
        $year = $this->requestedYear($request, $child, $activeYear);
        $enrollment = $child && $year ? $this->academicEnrollment($child, $year) : null;
        $settings = TimetableSetting::first() ?? TimetableSetting::make(TimetableSetting::defaults());
        $workingDays = collect($settings->working_days ?: TimetableSetting::defaults()['working_days']);
        $sessions = $enrollment?->school_group_id
            ? TimetableSession::with(['subject:id,title,title_ar', 'teacher:id,name', 'room:id,name'])
                ->where('academic_year_id', $year->id)->where('school_group_id', $enrollment->school_group_id)
                ->where('status', '!=', 'cancelled')->where('recurrence', 'weekly')->whereNull('parent_session_id')
                ->whereIn('day', $workingDays)->orderBy('day')->orderBy('start_time')->get()
            : collect();

        return Inertia::render('Parent/Timetable', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'context' => $child ? $this->childData($child, $year) : null,
            'academicYears' => $child ? $this->academicYears($child) : [],
            'days' => $workingDays->map(fn ($day) => ['value' => (int) $day, 'label' => $this->dayLabel((int) $day)])->values(),
            'timeSlots' => $settings->time_slots ?: [],
            'breaks' => collect($settings->breaks ?: [])->map(fn ($break) => [
                'name' => $break['name'], 'start_time' => $break['start_time'], 'end_time' => $break['end_time'], 'type' => $break['type'] ?? 'break',
            ])->values(),
            'sessions' => $sessions->map(fn ($session) => $this->sessionData($session))->values(),
            'hasEnrollment' => (bool) $enrollment,
            'hasGroup' => (bool) $enrollment?->school_group_id,
        ]);
    }

    public function absences(Request $request): Response
    {
        [$children, $child, $activeYear] = $this->context($request);
        $year = $this->requestedYear($request, $child, $activeYear);
        $enrollment = $child && $year ? $this->academicEnrollment($child, $year) : null;
        $periods = $year ? AcademicPeriod::where('academic_year_id', $year->id)->orderBy('number')->get() : collect();
        $period = $request->string('period')->toString();
        $subjectId = $request->integer('subject_id') ?: null;
        $justification = in_array($request->input('justification'), ['justified', 'pending', 'unjustified'], true) ? $request->input('justification') : null;
        $range = $this->absenceRange($period, $year, $periods);
        $query = AttendanceException::with(['timetableSession.subject:id,title', 'timetableSession.teacher:id,name'])
            ->where('student_id', $child?->id)->where('academic_year_id', $year?->id)
            ->whereIn('status', ['ABSENT', 'EXCUSED'])
            ->when($range, fn ($q) => $q->whereBetween('date', $range))
            ->when($subjectId, fn ($q) => $q->whereHas('timetableSession', fn ($session) => $session->where('course_id', $subjectId)))
            ->when($justification === 'justified', fn ($q) => $q->where(fn ($inner) => $inner->where('status', 'EXCUSED')->orWhereNotNull('justified_at')->orWhere(fn ($legacy) => $legacy->whereNotNull('justification')->whereNull('parent_justification_submitted_at'))))
            ->when($justification === 'pending', fn ($q) => $q->whereNotNull('parent_justification_submitted_at')->whereNull('justified_at')->where('status', '!=', 'EXCUSED'))
            ->when($justification === 'unjustified', fn ($q) => $q->where('status', '!=', 'EXCUSED')->whereNull('justified_at')->whereNull('parent_justification_submitted_at')->whereNull('justification'));
        $records = $child && $year ? $query->orderByDesc('date')->orderByDesc('created_at')->get() : collect();
        $all = $child && $year ? AttendanceException::where('student_id', $child->id)->where('academic_year_id', $year->id)->whereIn('status', ['ABSENT', 'EXCUSED'])->get() : collect();
        $currentPeriod = $periods->first(fn ($item) => today()->betweenIncluded($item->starts_on, $item->ends_on));
        $subjects = AttendanceException::with('timetableSession.subject:id,title')->where('student_id', $child?->id)
            ->where('academic_year_id', $year?->id)->whereIn('status', ['ABSENT', 'EXCUSED'])->get()
            ->pluck('timetableSession.subject')->filter()->unique('id')->sortBy('title')->values();

        return Inertia::render('Parent/Absences', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'context' => $child ? $this->childData($child, $year) : null,
            'academicYears' => $child ? $this->academicYears($child) : [],
            'periods' => $periods->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'number' => $item->number]),
            'subjects' => $subjects->map(fn ($item) => ['id' => $item->id, 'title' => $item->title]),
            'filters' => ['academic_year_id' => $year?->id, 'period' => $period ?: 'all', 'subject_id' => $subjectId, 'justification' => $justification],
            'summary' => [
                'total' => $all->count(),
                'period' => $currentPeriod ? $all->whereBetween('date', [$currentPeriod->starts_on, $currentPeriod->ends_on])->count() : 0,
                'unjustified' => $all->filter(fn ($item) => $this->justificationState($item) === 'unjustified')->count(),
            ],
            'absences' => $records->map(fn ($item) => $this->absenceData($item))->values(),
            'hasEnrollment' => (bool) $enrollment,
        ]);
    }

    public function justifyAbsence(Request $request, int $attendanceException, FilePondService $filePondService): RedirectResponse
    {
        [, $child] = $this->context($request);
        abort_unless($child, 403, 'Aucun enfant associé à ce compte.');

        $absence = AttendanceException::whereKey($attendanceException)
            ->where('student_id', $child->id)
            ->where('person_type', 'STUDENT')
            ->where('status', 'ABSENT')
            ->firstOrFail();
        abort_if($absence->justified_at || $absence->parent_justification_submitted_at || filled($absence->justification), 422, 'Une justification a déjà été envoyée pour cette absence.');

        $data = $request->validate([
            'justification' => ['required', 'string', 'min:3', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'attachment_temp_folders' => ['array', 'max:1'],
            'attachment_temp_folders.*' => ['string'],
        ]);
        $attachment = $request->file('attachment');
        $tempFolder = $data['attachment_temp_folders'][0] ?? null;
        $temp = $tempFolder ? TempFile::where('folder', $tempFolder)->first() : null;
        if ($temp) {
            abort_unless(in_array($temp->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) && $temp->size <= 10 * 1024 * 1024, 422, 'Pièce jointe invalide.');
            $path = 'parent-justifications/'.\Illuminate\Support\Str::uuid().'.'.pathinfo($temp->filename, PATHINFO_EXTENSION);
            Storage::disk('local')->put($path, Storage::disk(config('vilt-filepond.storage_disk'))->get($temp->path));
            $name = $temp->original_name;
            $filePondService->deleteTempFile($tempFolder);
        } else {
            $path = $attachment?->store('parent-justifications', 'local');
            $name = $attachment?->getClientOriginalName();
        }
        $absence->update([
            'justification' => trim($data['justification']),
            'parent_justification_submitted_at' => now(),
            'parent_justification_submitted_by' => $request->user()->id,
            'parent_justification_attachment_path' => $path,
            'parent_justification_attachment_name' => $name,
        ]);

        return back()->with('success', 'Justification envoyée à l’établissement pour validation.');
    }

    public function downloadAbsenceAttachment(Request $request, int $attendanceException)
    {
        [, $child] = $this->context($request);
        abort_unless($child, 403);
        $absence = AttendanceException::whereKey($attendanceException)->where('student_id', $child->id)->firstOrFail();
        abort_unless($absence->parent_justification_attachment_path, 404);

        return Storage::disk('local')->download($absence->parent_justification_attachment_path, $absence->parent_justification_attachment_name ?: 'justificatif');
    }

    public function grades(Request $request, ParentAcademicResultsService $results): Response
    {
        [$children, $child, $activeYear] = $this->context($request);
        $year = $this->requestedYear($request, $child, $activeYear);
        $enrollment = $child && $year ? $this->academicEnrollment($child, $year) : null;
        $periods = $year ? AcademicPeriod::where('academic_year_id', $year->id)->orderBy('number')->get() : collect();
        $period = $request->integer('academic_period_id') ? $periods->firstWhere('id', $request->integer('academic_period_id')) : $periods->first();
        $subjectId = $request->integer('subject_id') ?: null;
        $allRows = $enrollment && $enrollment->school_group_id ? $results->publishedResults($enrollment, $period) : collect();
        $rows = $subjectId ? $allRows->filter(fn ($row) => $row['subject']['id'] === $subjectId)->values() : $allRows;

        return Inertia::render('Parent/Grades', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'context' => $child ? $this->childData($child, $year) : null,
            'academicYears' => $child ? $this->academicYears($child) : [],
            'periods' => $periods->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'number' => $item->number]),
            'subjects' => $allRows->map(fn ($row) => $row['subject'])->values(),
            'results' => $rows,
            'filters' => ['academic_year_id' => $year?->id, 'academic_period_id' => $period?->id, 'subject_id' => $subjectId],
            'hasEnrollment' => (bool) $enrollment,
        ]);
    }

    public function exams(Request $request, ParentAcademicResultsService $results): Response
    {
        [$children, $child, $activeYear] = $this->context($request);
        $year = $this->requestedYear($request, $child, $activeYear);
        $enrollment = $child && $year ? $this->academicEnrollment($child, $year) : null;
        $periods = $year ? AcademicPeriod::where('academic_year_id', $year->id)->orderBy('number')->get() : collect();
        $period = $request->integer('academic_period_id') ? $periods->firstWhere('id', $request->integer('academic_period_id')) : null;
        $subjectId = $request->integer('subject_id') ?: null;
        $scope = in_array($request->input('scope'), ['upcoming', 'past', 'all'], true) ? $request->input('scope') : 'all';
        $exams = $enrollment && $enrollment->school_group_id ? $results->exams($enrollment, $period, $subjectId) : collect();
        $upcoming = $exams->filter(fn ($exam) => $exam['date'] >= today()->toDateString())->sortBy('date')->values();
        $past = $exams->filter(fn ($exam) => $exam['date'] < today()->toDateString())->sortByDesc('date')->values();

        return Inertia::render('Parent/Exams', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'context' => $child ? $this->childData($child, $year) : null,
            'academicYears' => $child ? $this->academicYears($child) : [],
            'periods' => $periods->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]),
            'subjects' => $exams->map(fn ($item) => ['id' => $item['subject_id'], 'title' => $item['subject']])->unique('id')->values(),
            'upcoming' => $scope === 'past' ? [] : $upcoming,
            'past' => $scope === 'upcoming' ? [] : $past,
            'filters' => ['academic_year_id' => $year?->id, 'academic_period_id' => $period?->id, 'subject_id' => $subjectId, 'scope' => $scope],
            'hasEnrollment' => (bool) $enrollment,
        ]);
    }

    public function module(Request $request, string $module): Response
    {
        $allowed = ['timetable', 'attendance', 'grades', 'report-cards', 'homework', 'events', 'announcements', 'payments', 'messages', 'profile'];
        abort_unless(in_array($module, $allowed, true), 404);
        [$children, $child, $year] = $this->context($request);

        return Inertia::render('Parent/Module', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'module' => $module,
        ]);
    }

    public function announcements(Request $request): Response
    {
        [$children, $child, $year] = $this->context($request);
        $query = $request->user()->portalNotifications()->where('type', 'announcement.new')
            ->whereHasMorph('related', [\App\Models\SchoolAnnouncement::class], fn ($query) => $query->where('status', 'published'));
        $announcements = $query->latest('occurred_at')->paginate(12)
            ->through(fn ($item) => [
                'id' => $item->id, 'title' => $item->title, 'message' => $item->message,
                'poster_url' => $item->data['poster_url'] ?? null,
                'published_at' => $item->occurred_at?->toIso8601String(),
                'read_at' => $item->read_at?->toIso8601String(),
            ]);

        return Inertia::render('Parent/Announcements', [
            'portal' => $this->portalProps($request, $children, $child, $year),
            'announcements' => $announcements,
        ]);
    }

    public function selectChild(Request $request): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'integer']]);
        $student = $this->parentChildren($request)->firstWhere('id', (int) $data['student_id']);
        abort_unless($student, 403, 'Cet enfant n’est pas associé à votre compte.');
        $request->session()->put('parent.selected_child_id', $student->id);

        $path = parse_url(url()->previous(), PHP_URL_PATH);

        return redirect(str_starts_with((string) $path, '/parent') ? $path : route('parent.dashboard', absolute: false));
    }

    private function context(Request $request, ?Student $preferred = null): array
    {
        $children = $this->parentChildren($request);
        $selectedId = $preferred?->id ?: (int) $request->session()->get('parent.selected_child_id');
        $child = $children->firstWhere('id', $selectedId) ?: $children->first();
        if ($child) {
            $request->session()->put('parent.selected_child_id', $child->id);
            $this->activateChildTenant($request, $child);
        }
        $year = AcademicYear::where('status', AcademicYearStatus::ACTIVE)->first();

        return [$children, $child, $year];
    }

    private function parentChildren(Request $request)
    {
        $memberships = MobileMembership::with('tenant:id,name,status')
            ->where('user_id', $request->user()->id)
            ->where('role', UserRole::PARENT->value)
            ->where('is_active', true)
            ->whereNotNull('parent_id')
            ->get()
            ->filter(fn ($membership) => $membership->tenant?->status === 'active');

        if ($memberships->isEmpty() && $request->user()->tenant_id && $request->user()->schoolParent) {
            $memberships = collect([(object) [
                'tenant_id' => $request->user()->tenant_id,
                'parent_id' => $request->user()->schoolParent->id,
                'tenant' => $request->user()->tenant,
            ]]);
        }

        $byParent = $memberships->keyBy('parent_id');
        $links = DB::table('parent_student')
            ->whereIn('parent_id', $memberships->pluck('parent_id'))
            ->get(['parent_id', 'student_id']);
        $accessByStudent = $links->keyBy('student_id')->map(fn ($link) => $byParent->get($link->parent_id));

        return Student::withoutGlobalScopes()
            ->whereIn('id', $links->pluck('student_id'))
            ->orderBy('first_name')->orderBy('last_name')->get()
            ->each(function (Student $student) use ($accessByStudent): void {
                $membership = $accessByStudent->get($student->id);
                $student->setAttribute('portal_tenant_id', (int) $membership->tenant_id);
                $student->setAttribute('portal_school_name', $membership->tenant?->name);
            });
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        abort_unless($this->parentChildren($request)->contains('id', $student->id), 403, 'Cet enfant n’est pas associé à votre compte.');
    }

    private function portalProps(Request $request, $children, ?Student $child, ?AcademicYear $year): array
    {
        return [
            'parent' => ['name' => $request->user()->name],
            'children' => $children->map(fn (Student $item) => $this->childDataInOwnSchool($item)),
            'selected_child_id' => $child?->id,
            'academic_year' => $year ? ['id' => $year->id, 'name' => $year->name] : null,
            'modules' => [
                'timetable' => Schema::hasTable('timetable_sessions'),
                'attendance' => Schema::hasTable('attendance_exceptions'),
                'grades' => Schema::hasTable('grades'),
                'report_cards' => Schema::hasTable('report_cards'),
                'events' => Schema::hasTable('academic_year_calendar_events'),
                'announcements' => Schema::hasTable('portal_notifications'),
                'payments' => Schema::hasTable('student_payments'),
                'messages' => Schema::hasTable('messages'),
                'homework' => Schema::hasTable('homework') || Schema::hasTable('assignments'),
            ],
        ];
    }

    private function childData(Student $student, ?AcademicYear $year): array
    {
        $enrollment = $this->academicEnrollment($student, $year);

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'identifier' => $student->id,
            'photo_url' => $student->photo_url,
            'academic_year' => $year?->name,
            'level' => $enrollment?->level?->name ?? $student->school_level,
            'group' => $enrollment?->group?->name,
            'main_teacher' => $enrollment?->group?->principalTeacher?->name,
            'site' => $enrollment?->group?->classroom?->site?->name,
            'school' => $student->getAttribute('portal_school_name'),
        ];
    }

    private function childDataInOwnSchool(Student $student): array
    {
        $context = app(TenantContext::class);
        $previous = $context->id();
        $context->set((int) $student->getAttribute('portal_tenant_id'));
        $year = AcademicYear::where('status', AcademicYearStatus::ACTIVE)->first();
        $data = $this->childData($student, $year);
        $previous ? $context->set($previous) : $context->clear();

        return $data;
    }

    private function activateChildTenant(Request $request, Student $student): void
    {
        $tenantId = (int) $student->getAttribute('portal_tenant_id');
        abort_unless($tenantId > 0, 403, 'Aucun établissement actif n’est associé à cet enfant.');
        app(TenantContext::class)->set($tenantId);
        $request->session()->put('parent.tenant_id', $tenantId);
        $request->attributes->set('tenant', \App\Models\Tenant::find($tenantId));
    }

    private function academicEnrollment(Student $student, ?AcademicYear $year): ?StudentAcademicEnrollment
    {
        if (! $year) {
            return null;
        }

        return $student->academicEnrollments()->where('academic_year_id', $year->id)
            ->with(['level', 'stream', 'group.principalTeacher:id,name', 'group.classroom.site'])->first();
    }

    private function today(Student $student, AcademicYear $year): array
    {
        $enrollment = $this->academicEnrollment($student, $year);
        $classes = $enrollment?->school_group_id ? $this->sessionsForDate($enrollment, today())->map(fn ($item) => $this->sessionData($item)) : collect();
        $absence = AttendanceException::with('timetableSession.subject:id,title')->where('student_id', $student->id)->where('academic_year_id', $year->id)
            ->whereDate('date', today())->whereIn('status', ['ABSENT', 'EXCUSED'])->latest()->first();
        $academicResults = app(ParentAcademicResultsService::class);
        $exam = $enrollment?->school_group_id ? $academicResults->nextExam($enrollment) : null;
        $grade = $enrollment?->school_group_id ? $academicResults->latestGrade($enrollment) : null;
        $observation = StudentObservation::where('student_id', $student->id)->whereNull('parent_id')->with('author:id,name')->latest()->first();
        $event = AcademicYearCalendarEvent::where('academic_year_id', $year->id)->whereDate('ends_on', '>=', today())->orderBy('starts_on')->first();

        return [
            'classes' => $classes,
            'absence' => $absence ? $this->absenceData($absence) : null,
            'exam' => $exam ? ['subject' => $exam['subject'], 'name' => $exam['name'], 'date' => $exam['date']] : null,
            'grade' => $grade ? ['subject' => $grade['subject'], 'value' => $grade['grade'], 'maximum' => $grade['maximum_grade']] : null,
            'observation' => $observation ? ['message' => $observation->message, 'author' => $observation->author?->name] : null,
            'event' => $event ? ['name' => $event->name, 'date' => $event->starts_on?->format('Y-m-d')] : null,
        ];
    }

    private function quickAccess(Student $student, AcademicYear $year): array
    {
        $absenceQuery = AttendanceException::where('student_id', $student->id)->where('academic_year_id', $year->id)->whereIn('status', ['ABSENT', 'EXCUSED']);
        $currentPeriod = AcademicPeriod::where('academic_year_id', $year->id)->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->first();
        $absences = $currentPeriod ? (clone $absenceQuery)->whereBetween('date', [$currentPeriod->starts_on, $currentPeriod->ends_on])->count() : 0;
        $unjustified = (clone $absenceQuery)->where('status', '!=', 'EXCUSED')->whereNull('justified_at')->whereNull('justification')->count();
        $report = ReportCard::where('student_id', $student->id)->where('academic_year_id', $year->id)->whereNotNull('published_at')->latest('published_at')->first();
        $payment = StudentPayment::where('student_id', $student->id)->latest('payment_date')->first();

        return ['absences' => $absences, 'unjustified_absences' => $unjustified, 'report_card' => $report ? ['period' => $report->period_key] : null, 'payment' => $payment ? ['amount' => $payment->amount, 'date' => $payment->payment_date?->format('Y-m-d')] : null];
    }

    private function activity(Request $request, Student $student, ?AcademicYear $year): array
    {
        $notifications = PortalNotification::where('recipient_id', $request->user()->id)
            ->when($year, fn ($query) => $query->whereBetween('occurred_at', [$year->start_date->copy()->startOfDay(), $year->end_date->copy()->endOfDay()]))
            ->where(function ($query) use ($student) {
                $query->where('related_id', $student->id)->orWhereNull('related_id');
            })->latest('occurred_at')->limit(8)->get()
            ->map(fn ($item) => ['id' => 'notification-'.$item->id, 'type' => $item->type, 'title' => $item->title, 'message' => $item->message, 'occurred_at' => $item->occurred_at]);
        $absences = $year ? AttendanceException::with('timetableSession.subject:id,title')->where('student_id', $student->id)
            ->where('academic_year_id', $year->id)->whereIn('status', ['ABSENT', 'EXCUSED'])->latest('updated_at')->limit(8)->get()
            ->map(fn ($item) => ['id' => 'absence-'.$item->id, 'type' => 'student.absence', 'title' => $this->justificationState($item) === 'approved' ? 'Absence justifiée' : 'Absence enregistrée', 'message' => trim(($item->timetableSession?->subject?->title ?? 'Journée').' · '.($item->timetableSession ? substr($item->timetableSession->start_time, 0, 5) : $item->date->format('d/m/Y'))), 'occurred_at' => $item->updated_at]) : collect();
        $enrollment = $year ? $this->academicEnrollment($student, $year) : null;
        $grades = $enrollment?->school_group_id ? collect(app(ParentAcademicResultsService::class)->publishedResults($enrollment))
            ->flatMap(fn ($subject) => $subject['assessments']->map(fn ($assessment) => ['id' => 'grade-'.$assessment['id'], 'type' => 'grade.published', 'title' => 'Nouvelle note publiée', 'message' => $subject['subject']['name'].' · '.($assessment['grade'] !== null ? $assessment['grade'].' / '.$assessment['maximum_grade'] : $assessment['type_label']), 'occurred_at' => $assessment['published_at']])) : collect();

        return $notifications->concat($absences)->concat($grades)->sortByDesc('occurred_at')->take(8)->values()->all();
    }

    private function requestedYear(Request $request, ?Student $student, ?AcademicYear $fallback): ?AcademicYear
    {
        if (! $student || ! $request->integer('academic_year_id')) {
            return $fallback;
        }

        return AcademicYear::whereKey($request->integer('academic_year_id'))
            ->whereHas('studentEnrollments', fn ($query) => $query->where('student_id', $student->id))->firstOrFail();
    }

    private function academicYears(Student $student)
    {
        return AcademicYear::whereHas('studentEnrollments', fn ($query) => $query->where('student_id', $student->id))
            ->orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'status']);
    }

    private function sessionData(TimetableSession $session): array
    {
        return ['id' => $session->id, 'day' => $session->day, 'subject' => $session->subject?->title, 'subject_ar' => $session->subject?->title_ar, 'start_time' => substr($session->start_time, 0, 5), 'end_time' => substr($session->end_time, 0, 5), 'teacher' => $session->teacher?->name, 'room' => $session->room?->name];
    }

    private function sessionsForDate(StudentAcademicEnrollment $enrollment, Carbon $date)
    {
        if (AcademicYearCalendarEvent::where('academic_year_id', $enrollment->academic_year_id)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->whereIn('applies_to', ['both', 'students'])->exists()) {
            return collect();
        }
        $replaced = TimetableSession::where('academic_year_id', $enrollment->academic_year_id)->whereDate('effective_date', $date)->whereNotNull('parent_session_id')->pluck('parent_session_id');

        return TimetableSession::with(['subject:id,title', 'teacher:id,name', 'room:id,name'])->where('academic_year_id', $enrollment->academic_year_id)
            ->where('school_group_id', $enrollment->school_group_id)->where('status', '!=', 'cancelled')
            ->where(fn ($query) => $query->where(fn ($weekly) => $weekly->where('recurrence', 'weekly')->where('day', $date->dayOfWeekIso)->whereNull('parent_session_id')->whereNotIn('id', $replaced))
                ->orWhere(fn ($once) => $once->where('recurrence', 'once')->whereDate('effective_date', $date)))
            ->orderBy('start_time')->get();
    }

    private function absenceRange(string $period, ?AcademicYear $year, $periods): ?array
    {
        if (! $year || ! $period || $period === 'all') {
            return null;
        }
        if ($period === 'week') {
            return [max(today()->startOfWeek(), $year->start_date), min(today()->endOfWeek(), $year->end_date)];
        }
        if ($period === 'month') {
            return [max(today()->startOfMonth(), $year->start_date), min(today()->endOfMonth(), $year->end_date)];
        }
        if (str_starts_with($period, 'period:') && ($item = $periods->firstWhere('id', (int) substr($period, 7)))) {
            return [$item->starts_on, $item->ends_on];
        }

        return null;
    }

    private function justificationState(AttendanceException $absence): string
    {
        if ($absence->status->value === 'EXCUSED' || $absence->justified_at) {
            return 'approved';
        }
        if ($absence->parent_justification_submitted_at) {
            return 'pending';
        }

        return filled($absence->justification) ? 'approved' : 'unjustified';
    }

    private function absenceData(AttendanceException $absence): array
    {
        $session = $absence->timetableSession;

        return ['id' => $absence->id, 'date' => $absence->date->format('Y-m-d'), 'subject' => $session?->subject?->title, 'start_time' => $session ? substr($session->start_time, 0, 5) : null, 'end_time' => $session ? substr($session->end_time, 0, 5) : null, 'teacher' => $session?->teacher?->name, 'status' => $absence->status->value, 'justification' => $this->justificationState($absence), 'justification_text' => $absence->justification, 'attachment_name' => $absence->parent_justification_attachment_name, 'attachment_url' => $absence->parent_justification_attachment_path ? route('parent.absences.attachment', $absence->id, false) : null];
    }

    private function dayLabel(int $day): string
    {
        return [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'][$day];
    }
}
