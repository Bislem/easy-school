<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\CourseLevel;
use App\Models\EnrollmentForm;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanTeacherAccess;
use App\Models\TrainingPlanGroup;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Services\AuthorizationService;
use App\Services\FormationEnrollmentService;
use App\Services\NotificationDispatcher;
use App\Services\SharedResourceBookings;
use App\Tenancy\TenantRule;
use Carbon\Carbon;
use App\Services\AttendanceService;
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
        $isAdmin = $this->canManage($request);
        $plans = TrainingPlan::query()
            ->with(['level:id,course_id,name,code,duration_hours,price', 'level.course:id,title,code', 'teacher:id,name', 'enrollmentForm:id,title,start_date,end_date', 'groups.classroom:id,name,capacity', 'groups.sessions:id,training_plan_group_id,starts_at,ends_at'])
            ->when(! $isAdmin, fn ($query) => $query->where(fn ($query) => $query
                ->where('teacher_id', $request->user()->id)
                ->orWhereHas('teacherAccesses', fn ($access) => $access->where('teacher_id', $request->user()->id))))
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query
                ->where(fn ($query) => $query->where('title', 'like', "%{$search}%")
                    ->orWhereHas('level.course', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('level', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(12)->withQueryString();

        $plans->through(function (TrainingPlan $plan) {
            $plan->setAttribute('sessions_count', $plan->groups->sum(fn ($group) => $group->sessions->count()));
            $plan->setAttribute('planned_hours', round($plan->groups->sum(fn ($group) => $group->sessions->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at))) / 60, 1));
            return $plan;
        });

        return Inertia::render('Admin/TrainingPlans/Index', [
            'plans' => $plans,
            'levels' => CourseLevel::with('course:id,title,code')->where('is_active', true)->whereHas('course', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(['id', 'course_id', 'name', 'code', 'duration_hours', 'price']),
            'forms' => EnrollmentForm::with('course:id,title,code')->where('is_active', true)->orderBy('start_date')->get(['id', 'course_id', 'teacher_id', 'classroom_id', 'title', 'start_date', 'end_date', 'max_students']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->when(! $isAdmin, fn ($query) => $query->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
            'filters' => $request->only(['search', 'status']),
            'access' => ['is_admin' => $isAdmin],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePlanning($request);
        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['form', 'level'])],
            'enrollment_form_id' => ['nullable', 'required_if:source_type,form', TenantRule::exists('enrollment_forms')],
            'course_level_id' => ['required', TenantRule::exists('course_levels')],
            'teacher_id' => ['nullable', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'title' => ['required', 'string', 'max:255'],
            'groups_count' => ['required', 'integer', 'min:1', 'max:100'],
            'classroom_id' => ['nullable', TenantRule::exists('classrooms')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $form = $validated['source_type'] === 'form' ? EnrollmentForm::findOrFail($validated['enrollment_form_id']) : null;
            $level = CourseLevel::findOrFail($validated['course_level_id']);
            if ($form && $level->course_id !== $form->course_id) {
                throw ValidationException::withMessages(['course_level_id' => 'Le niveau doit appartenir à la formation de la session.']);
            }
            $teacherId = $validated['teacher_id'] ?? $form?->teacher_id;
            if (! $teacherId) {
                throw ValidationException::withMessages(['teacher_id' => 'Choisissez le formateur principal.']);
            }
            $classroomId = $validated['classroom_id'] ?? $form?->classroom_id;
            $capacity = $classroomId ? Classroom::find($classroomId)?->capacity : null;

            $plan = TrainingPlan::create([
                'course_level_id' => $level->id, 'enrollment_form_id' => $form?->id,
                'teacher_id' => $teacherId, 'title' => $validated['title'],
                'status' => 'draft', 'notes' => $validated['notes'] ?? null,
            ]);
            $plan->teacherAccesses()->create(['teacher_id' => $teacherId, 'is_main' => true]);
            foreach (range(1, $validated['groups_count']) as $number) {
                $plan->groups()->create(['group_number' => $number, 'name' => "Groupe {$number}", 'classroom_id' => $classroomId, 'capacity' => $capacity]);
            }

            return $plan;
        });

        return to_route('admin.training-plans.show', $plan)->with('success', 'Planification créée. Ajoutez les étudiants aux groupes puis programmez les séances.');
    }

    public function show(Request $request, TrainingPlan $trainingPlan): Response
    {
        $this->authorizePlanning($request, $trainingPlan);
        $trainingPlan->load([
            'level.course', 'teacher:id,name,email,phone', 'enrollmentForm',
            'groups' => fn ($query) => $query->orderBy('group_number'),
            'groups.classroom',
            'groups.enrollments.student:id,first_name,last_name,email,phone,parent_phone,birth_date,school_level,status,notes,photo_path',
            'groups.sessions' => fn ($query) => $query
                ->with(['classroom:id,name,code', 'teacher:id,name', 'attendances:id,training_session_id,student_id,status,arrival_time,departure_time,is_justified,justification,notes'])
                ->orderBy('starts_at'),
        ]);
        $trainingPlan->groups->each(function ($group) {
            $group->setAttribute('planned_hours', round($group->sessions->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at)) / 60, 1));
            $records = $group->sessions->flatMap->attendances;
            $recordedSessions = $group->sessions->whereIn('attendance_status', ['completed', 'validated']);
            $expectedAttendances = $recordedSessions->count() * $group->enrollments->count();
            $absences = $records->whereIn('status', ['absent', 'excused'])->count();
            $repeated = $records->whereIn('status', ['absent', 'excused'])->groupBy('student_id')->filter(fn ($items) => $items->count() >= 3)->count();
            $group->setAttribute('attendance_stats', [
                'rate' => $expectedAttendances ? round(max(0, $expectedAttendances - $absences) / $expectedAttendances * 100, 1) : null,
                'repeated_absences' => $repeated,
                'missing_sessions' => $group->sessions->where('attendance_status', 'pending')->count(),
            ]);
        });

        return Inertia::render('Admin/TrainingPlans/Show', [
            'plan' => $trainingPlan,
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->when(! $this->canManage($request), fn ($query) => $query->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
            'students' => $this->canManage($request) ? Student::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'school_level', 'photo_path']) : [],
            'access' => $this->planningAccess($request, $trainingPlan),
        ]);
    }

    public function update(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureOpen($trainingPlan);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $enrolled = CourseEnrollment::where('status', ApplicationStatus::REGISTERED)->whereIn('training_plan_group_id', $trainingPlan->groups()->select('id'));
        if ($validated['status'] === 'cancelled' && (clone $enrolled)->exists()) {
            throw ValidationException::withMessages(['status' => 'Retirez ou annulez les inscriptions des groupes avant d’annuler la planification.']);
        }
        DB::transaction(function () use ($trainingPlan, $validated, $enrolled, $request) {
            $teacherChanged = (int) $trainingPlan->teacher_id !== (int) $validated['teacher_id'];
            $trainingPlan->update($validated);
            if ($teacherChanged) {
                $trainingPlan->teacherAccesses()->where('is_main', true)->delete();
                $trainingPlan->teacherAccesses()->updateOrCreate(
                    ['teacher_id' => $validated['teacher_id']],
                    ['is_main' => true, 'can_manage_groups' => false, 'can_add_sessions' => false, 'can_record_attendance' => false],
                );
            }
            if ($validated['status'] === 'completed') {
                // Finishing a plan finishes its students' enrollments; the plan becomes read-only.
                foreach ($enrolled->get() as $enrollment) {
                    $enrollment->update(['status' => ApplicationStatus::COMPLETED]);
                    $enrollment->histories()->create(['user_id' => $request->user()->id, 'event' => 'status_changed', 'from_status' => 'registered', 'to_status' => 'completed', 'description' => 'Planification terminée.']);
                }
            }
        });
        return back()->with('success', 'Planification mise à jour.');
    }

    public function settings(Request $request, TrainingPlan $trainingPlan): Response
    {
        $this->authorizeAdmin($request);
        $trainingPlan->teacherAccesses()->updateOrCreate(
            ['teacher_id' => $trainingPlan->teacher_id],
            ['is_main' => true],
        );
        $trainingPlan->load(['level.course:id,title,code', 'teacher:id,name,email', 'teacherAccesses' => fn ($query) => $query->with('teacher:id,name,email')->orderByDesc('is_main')->orderBy('id')]);
        $assignedIds = $trainingPlan->teacherAccesses->pluck('teacher_id');

        return Inertia::render('Admin/TrainingPlans/Settings', [
            'plan' => $trainingPlan,
            'availableTeachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->whereNotIn('id', $assignedIds)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function updateMainAccess(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $access = $trainingPlan->teacherAccesses()->where('teacher_id', $trainingPlan->teacher_id)->firstOrFail();
        $access->update($this->validateTeacherAccess($request));
        return back()->with('success', 'Accès du formateur principal mis à jour.');
    }

    public function storeTeacherAccess(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $validated = $request->validate([
            'teacher_id' => [
                'required',
                TenantRule::exists('users')->where('role', UserRole::TEACHER->value),
                Rule::unique('training_plan_teacher_accesses', 'teacher_id')->where('training_plan_id', $trainingPlan->id),
            ],
            ...$this->teacherAccessRules(),
        ]);
        abort_if((int) $validated['teacher_id'] === (int) $trainingPlan->teacher_id, 422, 'Ce formateur est déjà le formateur principal.');
        $trainingPlan->teacherAccesses()->create([...$validated, 'is_main' => false]);
        return back()->with('success', 'Formateur ajouté à la planification.');
    }

    public function updateTeacherAccess(Request $request, TrainingPlan $trainingPlan, TrainingPlanTeacherAccess $access): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($access->training_plan_id === $trainingPlan->id && ! $access->is_main, 404);
        $access->update($this->validateTeacherAccess($request));
        return back()->with('success', 'Accès du formateur mis à jour.');
    }

    public function destroyTeacherAccess(Request $request, TrainingPlan $trainingPlan, TrainingPlanTeacherAccess $access): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($access->training_plan_id === $trainingPlan->id && ! $access->is_main, 404);
        $access->delete();
        return back()->with('success', 'Accès supplémentaire supprimé.');
    }

    public function storeGroup(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'groups');
        $this->ensureOpen($trainingPlan);
        $validated = $this->validateGroup($request);
        $number = ((int) $trainingPlan->groups()->max('group_number')) + 1;
        $trainingPlan->groups()->create([...$validated, 'group_number' => $number]);
        return back()->with('success', 'Groupe ajouté.');
    }

    public function updateGroup(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'groups');
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        $validated = $this->validateGroup($request);
        if ($validated['capacity'] && $group->enrollments()->count() > $validated['capacity']) {
            throw ValidationException::withMessages(['capacity' => 'La capacité ne peut pas être inférieure au nombre d’étudiants du groupe.']);
        }
        $group->update($validated);
        return back()->with('success', 'Groupe mis à jour.');
    }

    public function destroyGroup(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'groups');
        $this->ensureGroup($trainingPlan, $group);
        if ($trainingPlan->groups()->count() <= 1) {
            return back()->withErrors(['group' => 'Une planification doit conserver au moins un groupe.']);
        }
        if ($group->enrollments()->exists()) {
            return back()->withErrors(['group' => 'Déplacez les étudiants avant de supprimer ce groupe.']);
        }
        // Sessions with recorded attendance or validated hours feed attendance history and payroll.
        $hasHistory = $group->sessions()->where(fn ($q) => $q->where('status', 'completed')->orWhereIn('attendance_status', ['completed', 'validated'])->orWhereHas('attendances')->orWhereHas('teacherAttendance'))->exists();
        if ($hasHistory) {
            return back()->withErrors(['group' => 'Ce groupe a des séances avec présences enregistrées : il ne peut pas être supprimé.']);
        }
        $group->delete();
        return back()->with('success', 'Groupe et ses séances non réalisées supprimés.');
    }

    public function storeSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'sessions');
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        $validated = $this->validateSession($request, $trainingPlan, $group);
        $this->assertNoConflict($validated, $group);
        $this->assertDuration($trainingPlan, $group, $validated);
        $group->sessions()->create($validated);
        return back()->with('success', 'Séance planifiée avec succès.');
    }

    public function updateSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'sessions');
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        abort_if($session->status === 'completed' || $session->attendance_locked_at || $session->attendance_status === 'validated', 422, 'Une séance validée définitivement ne peut plus être modifiée.');
        $validated = $this->validateSession($request, $trainingPlan, $group);
        if(! $session->starts_at->equalTo(\Carbon\Carbon::parse($validated['starts_at'])) || ! $session->ends_at->equalTo(\Carbon\Carbon::parse($validated['ends_at']))) $validated['status']='postponed';
        $this->assertNoConflict($validated, $group, $session);
        $this->assertDuration($trainingPlan, $group, $validated, $session);
        $before=$session->only(['starts_at','ends_at','classroom_id','teacher_id']);$session->update($validated);$changed=array_keys(array_diff_assoc($session->only(array_keys($before)),$before));
        if($changed){$type=in_array('teacher_id',$changed)?'session.teacher_changed':(in_array('classroom_id',$changed)?'session.room_changed':((in_array('starts_at',$changed)||in_array('ends_at',$changed))?'session.postponed':'planning.changed'));$this->notifySessionAudience($trainingPlan,$group,$session,$type,'Planning mis à jour','Une séance de '.$trainingPlan->title.' a été modifiée.');}
        return back()->with('success', 'Séance mise à jour.');
    }

    public function destroySession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'sessions');
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        abort_if($session->status === 'completed' || $session->attendance_locked_at || $session->attendance_status === 'validated', 422, 'Une séance validée définitivement ne peut plus être supprimée.');
        $this->notifySessionAudience($trainingPlan,$group,$session,'session.cancelled','Séance annulée','Une séance de '.$trainingPlan->title.' a été annulée.');$session->update(['status'=>'cancelled']);
        return back()->with('success', 'Séance annulée et conservée dans l’historique.');
    }

    public function completeSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'attendance');
        $this->ensureGroup($trainingPlan,$group); abort_unless($session->training_plan_group_id===$group->id,404);
        abort_if($session->status==='cancelled',422,'Une séance annulée ne peut pas être terminée.');
        app(AttendanceService::class)->validate($session, $request->user()->id);
        return back()->with('success','Séance validée définitivement. Le formateur est marqué présent et ses heures sont disponibles pour la paie.');
    }

    /** Add students to a group: a student already registered in the plan's session is placed, others get a new registration. */
    public function addStudent(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, FormationEnrollmentService $enrollments): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', TenantRule::exists('students')],
        ]);
        $students = Student::whereIn('id', $validated['student_ids'])->get();
        if (! $group->hasRoomFor($students->count())) {
            throw ValidationException::withMessages(['student_ids' => 'La sélection dépasse la capacité disponible du groupe.']);
        }

        DB::transaction(function () use ($request, $trainingPlan, $group, $students, $enrollments) {
            foreach ($students as $student) {
                $existing = $trainingPlan->enrollment_form_id
                    ? CourseEnrollment::where('student_id', $student->id)->where('enrollment_form_id', $trainingPlan->enrollment_form_id)->where('status', ApplicationStatus::REGISTERED)->first()
                    : null;
                if ($existing && ! $existing->training_plan_group_id) {
                    $enrollments->place($existing, $group, $request->user()->id);

                    continue;
                }
                $enrollment = new CourseEnrollment(['enrollment_form_id' => $trainingPlan->enrollment_form_id, 'level' => $student->school_level]);
                $enrollments->register($enrollment, $student, $group, $request->user()->id, 'Étudiant affecté depuis la planification.');
            }
        });

        return back()->with('success', $students->count().' étudiant(s) ajouté(s) au groupe.');
    }

    public function moveStudent(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, CourseEnrollment $enrollment, FormationEnrollmentService $enrollments): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        abort_unless($enrollment->training_plan_group_id === $group->id, 404);
        $validated = $request->validate(['training_plan_group_id' => ['required', 'integer']]);
        $target = $trainingPlan->groups()->findOrFail($validated['training_plan_group_id']);
        $enrollments->place($enrollment, $target, $request->user()->id);

        return back()->with('success', 'Étudiant déplacé vers '.$target->name.'.');
    }

    public function updateStudent(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, CourseEnrollment $enrollment): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        abort_unless($enrollment->training_plan_group_id === $group->id && $enrollment->student_id, 404);
        $student = $enrollment->student;
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'parent_phone' => ['nullable', 'string', 'max:50'],
            'school_level' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $student->update($validated);
        $enrollment->update(['first_name' => $student->first_name, 'last_name' => $student->last_name, 'email' => $student->email ?: $enrollment->email, 'phone' => $student->phone ?: $enrollment->phone, 'parent_phone' => $student->parent_phone, 'level' => $student->school_level]);
        $student->histories()->create(['user_id' => $request->user()->id, 'event' => 'profile_updated', 'description' => 'Dossier modifié depuis la planification.']);
        return back()->with('success', 'Dossier étudiant mis à jour.');
    }

    public function recordAttendance(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'attendance');
        $this->ensureGroup($trainingPlan, $group);
        $this->ensureOpen($trainingPlan);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        abort_if($session->attendance_locked_at || $session->attendance_status === 'validated', 422, 'Les absences de cette séance sont verrouillées.');
        $validated = $request->validate([
            'student_ids' => ['present', 'array'],
            'student_ids.*' => ['integer', 'distinct'],
        ]);
        app(AttendanceService::class)->syncTrainingSessionAbsences($session, $validated['student_ids'] ?? [], $request->user()->id);

        return back()->with('success', 'Absences de la séance enregistrées. Les autres étudiants sont considérés présents.');
    }

    private function notifySessionAudience(TrainingPlan $plan,TrainingPlanGroup $group,TrainingSession $session,string $type,string $title,string $message): void
    {
        $session->loadMissing('classroom');
        $message = collect([
            $message,
            'Séance : '.$session->title,
            'Groupe : '.$group->name,
            $session->starts_at ? 'Date : '.$session->starts_at->format('d/m/Y à H:i') : null,
            $session->classroom?->name ? 'Salle : '.$session->classroom->name : null,
        ])->filter()->implode(' • ');
        $users=collect([$session->teacher_id,$plan->teacher_id]);
        $enrollments=CourseEnrollment::with(['student.user','student.parents.user'])->where('training_plan_group_id',$group->id)->where('status','registered')->get();
        foreach($enrollments as $enrollment)$users=$users->merge([$enrollment->student?->user_id])->merge($enrollment->student?->parents?->pluck('user_id')??[]);
        foreach($users->filter()->unique() as $userId)app(NotificationDispatcher::class)->send((int)$userId,$type,$title,$message,$session,['starts_at'=>$session->starts_at,'group'=>$group->name]);
    }

    private function validateGroup(Request $request): array
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100'], 'classroom_id' => ['nullable', TenantRule::exists('classrooms')], 'capacity' => ['nullable', 'integer', 'min:1']]);
        $validated['classroom_id'] ??= null;
        $validated['capacity'] ??= null;
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
            'title' => ['required', 'string', 'max:255'], 'classroom_id' => ['required', TenantRule::exists('classrooms')],
            'teacher_id' => ['required', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! $this->canManage($request)) {
            $validated['teacher_id'] = $request->user()->id;
        }
        if ($plan->enrollmentForm && ($request->date('starts_at')->toDateString() < $plan->enrollmentForm->start_date->toDateString() || $request->date('ends_at')->toDateString() > $plan->enrollmentForm->end_date->toDateString())) {
            throw ValidationException::withMessages(['starts_at' => 'La séance doit être comprise dans les dates du formulaire d’inscription.']);
        }
        return $validated;
    }

    private function assertNoConflict(array $data, TrainingPlanGroup $group, ?TrainingSession $except = null): void
    {
        $overlap = fn ($query) => $query->where('status', '!=', 'cancelled')->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->when($except, fn ($query) => $query->whereKeyNot($except->id));
        if ($overlap(TrainingSession::where('classroom_id', $data['classroom_id']))->exists()) {
            throw ValidationException::withMessages(['classroom_id' => 'Cette salle est déjà occupée pendant cet horaire.']);
        }
        if ($overlap(TrainingSession::where('teacher_id', $data['teacher_id']))->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Ce formateur anime déjà une autre séance pendant cet horaire.']);
        }
        if ($overlap($group->sessions())->exists()) {
            throw ValidationException::withMessages(['starts_at' => 'Ce groupe a déjà une séance pendant cet horaire.']);
        }
        $school = app(SharedResourceBookings::class)->timetableConflicts(Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']), (int) $data['classroom_id'], (int) $data['teacher_id']);
        if ($slot = $school->first()) {
            $field = (int) $slot->classroom_id === (int) $data['classroom_id'] ? 'classroom_id' : 'teacher_id';
            throw ValidationException::withMessages([$field => ($field === 'classroom_id' ? 'Cette salle' : 'Ce formateur').' est pris par l’emploi du temps scolaire ('.($slot->group?->name ?? 'classe').', '.substr((string) $slot->start_time, 0, 5).'–'.substr((string) $slot->end_time, 0, 5).').']);
        }
    }

    private function assertDuration(TrainingPlan $plan, TrainingPlanGroup $group, array $data, ?TrainingSession $except = null): void
    {
        $planned = $group->sessions()->where('status', '!=', 'cancelled')->when($except, fn ($query) => $query->whereKeyNot($except->id))->get()->sum(fn ($session) => $session->starts_at->diffInMinutes($session->ends_at));
        $newMinutes = Carbon::parse($data['starts_at'])->diffInMinutes(Carbon::parse($data['ends_at']));
        if ($planned + $newMinutes > $plan->level->duration_hours * 60) {
            throw ValidationException::withMessages(['ends_at' => "La durée totale planifiée dépasserait les {$plan->level->duration_hours} heures du niveau."]);
        }
    }

    private function ensureGroup(TrainingPlan $plan, TrainingPlanGroup $group): void
    {
        abort_unless($group->training_plan_id === $plan->id, 404);
    }

    /** Completed or cancelled plans are kept as history and can no longer be changed. */
    private function ensureOpen(TrainingPlan $plan): void
    {
        abort_if(in_array($plan->status, ['completed', 'cancelled'], true), 422, 'Cette planification est clôturée : elle ne peut plus être modifiée.');
    }

    private function canManage(Request $request): bool
    {
        return app(AuthorizationService::class)->allows($request->user(), 'timetables.manage');
    }

    private function planningAccess(Request $request, TrainingPlan $plan): array
    {
        $manager = $this->canManage($request);
        $access = $manager ? null : $plan->teacherAccesses()->where('teacher_id', $request->user()->id)->first();
        $open = ! in_array($plan->status, ['completed', 'cancelled'], true);

        return [
            'is_admin' => $manager,
            'is_open' => $open,
            'manage_students' => $manager && $open,
            'manage_groups' => $open && ($manager || (bool) $access?->can_manage_groups),
            'add_sessions' => $open && ($manager || (bool) $access?->can_add_sessions),
            'record_attendance' => $open && ($manager || (bool) $access?->can_record_attendance),
        ];
    }

    /** Planning managers (timetables.manage) may do everything; a plan's teachers only what their access allows. */
    private function authorizePlanning(Request $request, ?TrainingPlan $plan = null, ?string $ability = null): void
    {
        if ($this->canManage($request)) {
            return;
        }
        abort_unless($plan, 403);
        $access = $plan->teacherAccesses()->where('teacher_id', $request->user()->id)->first();
        abort_unless($access || $plan->teacher_id === $request->user()->id, 403, 'Vous n’avez pas accès à cette planification.');
        if ($ability !== null) {
            $key = match ($ability) { 'groups' => 'manage_groups', 'sessions' => 'add_sessions', 'attendance' => 'record_attendance' };
            abort_unless($this->planningAccess($request, $plan)[$key], 403, 'Cette action n’est pas autorisée pour les enseignants.');
        } else {
            abort_if($request->method() !== 'GET', 403);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function teacherAccessRules(): array
    {
        return [
            'can_manage_groups' => ['required', 'boolean'],
            'can_add_sessions' => ['required', 'boolean'],
            'can_record_attendance' => ['required', 'boolean'],
        ];
    }

    private function validateTeacherAccess(Request $request): array
    {
        return $request->validate($this->teacherAccessRules());
    }
}
