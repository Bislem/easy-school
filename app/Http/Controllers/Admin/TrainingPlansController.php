<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Course;
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
use App\Services\NotificationDispatcher;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrainingPlansController extends Controller
{
    public function index(Request $request): Response
    {
        $isAdmin = $request->user()->role === UserRole::ADMIN;
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
            'forms' => EnrollmentForm::with('course:id,title,code')->where('is_active', true)->orderBy('start_date')->get(['id', 'course_id', 'teacher_id', 'classroom_id', 'title', 'start_date', 'end_date', 'groups_count', 'students_per_group']),
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->when($request->user()->role === UserRole::TEACHER, fn ($query) => $query->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']),
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
            'enrollment_form_id' => ['nullable', 'required_if:source_type,form', 'exists:enrollment_forms,id'],
            'course_level_id' => ['required', 'exists:course_levels,id'],
            'teacher_id' => ['nullable', 'required_if:source_type,level', Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value)],
            'title' => ['required', 'string', 'max:255'],
            'groups_count' => ['nullable', 'required_if:source_type,level', 'integer', 'min:1', 'max:100'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $plan = DB::transaction(function () use ($validated) {
            $form = $validated['source_type'] === 'form' ? EnrollmentForm::findOrFail($validated['enrollment_form_id']) : null;
            $level = CourseLevel::findOrFail($validated['course_level_id']);
            if ($form && $level->course_id !== $form->course_id) {
                throw ValidationException::withMessages(['course_level_id' => 'Le niveau doit appartenir à la formation du formulaire.']);
            }
            $teacherId = $form?->teacher_id ?? $validated['teacher_id'];
            $groupsCount = $form?->groups_count ?? $validated['groups_count'];
            $classroomId = $form?->classroom_id ?? ($validated['classroom_id'] ?? null);
            $capacity = $form ? $form->groupCapacity() : ($classroomId ? Classroom::find($classroomId)?->capacity : null);

            $plan = TrainingPlan::create([
                'course_level_id' => $level->id, 'enrollment_form_id' => $form?->id,
                'teacher_id' => $teacherId, 'title' => $validated['title'],
                'status' => 'draft', 'notes' => $validated['notes'] ?? null,
            ]);
            $plan->teacherAccesses()->create(['teacher_id' => $teacherId, 'is_main' => true]);
            foreach (range(1, $groupsCount) as $number) {
                $group = $plan->groups()->create(['group_number' => $number, 'name' => "Groupe {$number}", 'classroom_id' => $classroomId, 'capacity' => $capacity]);
                if ($form) {
                    $form->enrollments()->where('status', 'registered')->where('group_number', $number)
                        ->update(['training_plan_group_id' => $group->id]);
                }
            }
            return $plan;
        });

        CourseEnrollment::with(['student.parents.user', 'trainingPlanGroup'])
            ->whereIn('training_plan_group_id', $plan->groups()->pluck('id'))
            ->whereNotNull('student_id')->get()->each(function (CourseEnrollment $enrollment) use ($plan) {
                foreach ($enrollment->student?->parents?->pluck('user')->filter()->unique('id') ?? [] as $parent) {
                    app(NotificationDispatcher::class)->send($parent, 'student.plan_assigned', 'Nouvelle planification', $enrollment->student->full_name.' a été affecté(e) à la planification « '.$plan->title.' », groupe « '.$enrollment->trainingPlanGroup?->name.' ».', $enrollment, ['url'=>'/portal/children/'.$enrollment->student_id]);
                }
            });

        return to_route('admin.training-plans.show', $plan)->with('success', 'Planification créée. Vous pouvez maintenant programmer les séances.');
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
            $records=$group->sessions->flatMap->attendances;$present=$records->whereIn('status',['present','late'])->count();
            $repeated=$records->where('status','absent')->groupBy('student_id')->filter(fn($items)=>$items->count()>=3)->count();
            $group->setAttribute('attendance_stats',['rate'=>$records->count()?round($present/$records->count()*100,1):null,'repeated_absences'=>$repeated,'missing_sessions'=>$group->sessions->where('attendance_status','pending')->count()]);
        });

        return Inertia::render('Admin/TrainingPlans/Show', [
            'plan' => $trainingPlan,
            'teachers' => User::where('role', UserRole::TEACHER->value)->where('is_active', true)->when($request->user()->role === UserRole::TEACHER, fn ($query) => $query->whereKey($request->user()->id))->orderBy('name')->get(['id', 'name']),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity']),
            'students' => $request->user()->role === UserRole::ADMIN ? Student::where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email', 'phone', 'birth_date', 'school_level', 'photo_path']) : [],
            'access' => $this->planningAccess($request, $trainingPlan),
        ]);
    }

    public function update(Request $request, TrainingPlan $trainingPlan): RedirectResponse
    {
        $this->authorizePlanning($request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'teacher_id' => ['required', Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value)],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($trainingPlan, $validated) {
            $teacherChanged = (int) $trainingPlan->teacher_id !== (int) $validated['teacher_id'];
            $trainingPlan->update($validated);
            if ($teacherChanged) {
                $trainingPlan->teacherAccesses()->where('is_main', true)->delete();
                $trainingPlan->teacherAccesses()->updateOrCreate(
                    ['teacher_id' => $validated['teacher_id']],
                    ['is_main' => true, 'can_manage_groups' => false, 'can_add_sessions' => false, 'can_record_attendance' => false],
                );
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
                Rule::exists('users', 'id')->where('role', UserRole::TEACHER->value),
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
        $validated = $this->validateGroup($request);
        $number = ((int) $trainingPlan->groups()->max('group_number')) + 1;
        $trainingPlan->groups()->create([...$validated, 'group_number' => $number]);
        return back()->with('success', 'Groupe ajouté.');
    }

    public function updateGroup(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'groups');
        $this->ensureGroup($trainingPlan, $group);
        $group->update($this->validateGroup($request));
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
        $group->delete();
        return back()->with('success', 'Groupe et ses séances supprimés.');
    }

    public function storeSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request, $trainingPlan, 'sessions');
        $this->ensureGroup($trainingPlan, $group);
        $validated = $this->validateSession($request, $trainingPlan, $group);
        $this->assertNoConflict($validated);
        $this->assertDuration($trainingPlan, $group, $validated);
        $group->sessions()->create($validated);
        return back()->with('success', 'Séance planifiée avec succès.');
    }

    public function updateSession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        abort_unless($session->training_plan_group_id === $group->id, 404);
        abort_if($session->status === 'completed' || $session->attendance_locked_at || $session->attendance_status === 'validated', 422, 'Une séance validée définitivement ne peut plus être modifiée.');
        $validated = $this->validateSession($request, $trainingPlan, $group);
        if(! $session->starts_at->equalTo(\Carbon\Carbon::parse($validated['starts_at'])) || ! $session->ends_at->equalTo(\Carbon\Carbon::parse($validated['ends_at']))) $validated['status']='postponed';
        $this->assertNoConflict($validated, $session);
        $this->assertDuration($trainingPlan, $group, $validated, $session);
        $before=$session->only(['starts_at','ends_at','classroom_id','teacher_id']);$session->update($validated);$changed=array_keys(array_diff_assoc($session->only(array_keys($before)),$before));
        if($changed){$type=in_array('teacher_id',$changed)?'session.teacher_changed':(in_array('classroom_id',$changed)?'session.room_changed':((in_array('starts_at',$changed)||in_array('ends_at',$changed))?'session.postponed':'planning.changed'));$this->notifySessionAudience($trainingPlan,$group,$session,$type,'Planning mis à jour','Une séance de '.$trainingPlan->title.' a été modifiée.');}
        return back()->with('success', 'Séance mise à jour.');
    }

    public function destroySession(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, TrainingSession $session): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
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

    public function addStudent(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
        ]);
        $students = Student::whereIn('id', $validated['student_ids'])->get();

        DB::transaction(function () use ($request, $trainingPlan, $group, $students) {
            $lockedGroup = TrainingPlanGroup::query()->lockForUpdate()->findOrFail($group->id);
            if ($lockedGroup->capacity && $lockedGroup->enrollments()->count() + $students->count() > $lockedGroup->capacity) {
                throw ValidationException::withMessages(['student_ids' => 'La sélection dépasse la capacité disponible du groupe.']);
            }
            $existingIds = CourseEnrollment::whereIn('student_id', $students->pluck('id'))
                ->where(function ($query) use ($trainingPlan) {
                    $query->whereHas('trainingPlanGroup', fn ($group) => $group->where('training_plan_id', $trainingPlan->id));
                    if ($trainingPlan->enrollment_form_id) $query->orWhere('enrollment_form_id', $trainingPlan->enrollment_form_id);
                })->pluck('student_id');
            if ($existingIds->isNotEmpty()) {
                throw ValidationException::withMessages(['student_ids' => 'Un ou plusieurs étudiants sélectionnés sont déjà inscrits dans cette planification.']);
            }

            foreach ($students as $student) {
                $enrollment = CourseEnrollment::create([
                    'enrollment_form_id' => $trainingPlan->enrollment_form_id,
                    'training_plan_group_id' => $lockedGroup->id,
                    'student_id' => $student->id,
                    'status' => 'registered', 'first_name' => $student->first_name, 'last_name' => $student->last_name,
                    'email' => $student->email ?: "student-{$student->id}@internal.invalid", 'phone' => $student->phone ?: '-',
                    'parent_phone' => $student->parent_phone, 'birth_date' => $student->birth_date, 'level' => $student->school_level,
                    'confirmation_token' => (string) Str::uuid(), 'confirmed_at' => now(), 'approved_at' => now(), 'registered_at' => now(),
                    'group_number' => $lockedGroup->group_number, 'formation_price' => $trainingPlan->level->price ?? 0,
                    'final_price' => $trainingPlan->level->price ?? 0, 'remaining_balance' => $trainingPlan->level->price ?? 0,
                ]);
                $enrollment->histories()->create(['user_id' => $request->user()->id, 'event' => 'assigned_to_group', 'description' => 'Étudiant affecté depuis la planification.', 'metadata' => ['training_plan_group_id' => $lockedGroup->id]]);
            }
        });

        return back()->with('success', $students->count().' étudiant(s) ajouté(s) au groupe.');
    }

    public function moveStudent(Request $request, TrainingPlan $trainingPlan, TrainingPlanGroup $group, CourseEnrollment $enrollment): RedirectResponse
    {
        $this->authorizePlanning($request);
        $this->ensureGroup($trainingPlan, $group);
        abort_unless($enrollment->training_plan_group_id === $group->id, 404);
        $validated = $request->validate(['training_plan_group_id' => ['required', 'integer']]);
        $target = $trainingPlan->groups()->findOrFail($validated['training_plan_group_id']);
        if ($target->isNot($group)) $this->assertGroupCapacity($target);
        $from = $group->id;
        $enrollment->update(['training_plan_group_id' => $target->id, 'group_number' => $target->group_number]);
        $enrollment->histories()->create(['user_id' => $request->user()->id, 'event' => 'group_changed', 'description' => 'Groupe modifié depuis la planification.', 'metadata' => ['from_group_id' => $from, 'to_group_id' => $target->id]]);
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
            'email' => ['nullable', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student->id)],
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
        abort_unless($session->training_plan_group_id === $group->id, 404);
        if ($session->attendance_status !== 'pending' || $session->attendances()->exists()) {
            throw ValidationException::withMessages(['attendance' => 'Les présences ont déjà été saisies. Toute correction doit être effectuée dans le module Présences avec un justificatif.']);
        }
        $validated = $request->validate(['attendances' => ['required', 'array'], 'attendances.*' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])], 'validate_session' => ['sometimes', 'boolean']]);
        $records=collect($validated['attendances'])->map(fn($status,$studentId)=>['student_id'=>(int)$studentId,'status'=>$status])->values()->all();
        app(AttendanceService::class)->recordStudents($session,$records,$request->user()->id);
        if ($validated['validate_session'] ?? false) {
            app(AttendanceService::class)->validate($session, $request->user()->id);
            return back()->with('success', 'Présences et séance validées définitivement. Le formateur est marqué présent.');
        }
        return back()->with('success', 'Présences enregistrées une fois. La séance reste à valider.');
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
        $enrollments=CourseEnrollment::with(['student.user','student.parents.user'])->where('enrollment_form_id',$plan->enrollment_form_id)->where('group_number',$group->group_number)->where('status','registered')->get();
        foreach($enrollments as $enrollment)$users=$users->merge([$enrollment->student?->user_id])->merge($enrollment->student?->parents?->pluck('user_id')??[]);
        foreach($users->filter()->unique() as $userId)app(NotificationDispatcher::class)->send((int)$userId,$type,$title,$message,$session,['starts_at'=>$session->starts_at,'group'=>$group->name]);
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
        if ($request->user()->role === UserRole::TEACHER) {
            $validated['teacher_id'] = $request->user()->id;
        }
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
        if ($planned + $newMinutes > $plan->level->duration_hours * 60) {
            throw ValidationException::withMessages(['ends_at' => "La durée totale planifiée dépasserait les {$plan->level->duration_hours} heures du niveau."]);
        }
    }

    private function ensureGroup(TrainingPlan $plan, TrainingPlanGroup $group): void
    {
        abort_unless($group->training_plan_id === $plan->id, 404);
    }

    private function assertGroupCapacity(TrainingPlanGroup $group): void
    {
        if ($group->capacity && $group->enrollments()->count() >= $group->capacity) {
            throw ValidationException::withMessages(['student_id' => 'Ce groupe a atteint sa capacité maximale.']);
        }
    }

    private function planningAccess(Request $request, TrainingPlan $plan): array
    {
        $admin = $request->user()->role === UserRole::ADMIN;
        $access = $admin ? null : $plan->teacherAccesses()->where('teacher_id', $request->user()->id)->first();

        return [
            'is_admin' => $admin,
            'manage_groups' => $admin || (bool) $access?->can_manage_groups,
            'add_sessions' => $admin || (bool) $access?->can_add_sessions,
            'record_attendance' => $admin || (bool) $access?->can_record_attendance,
        ];
    }

    private function authorizePlanning(Request $request, ?TrainingPlan $plan = null, ?string $ability = null): void
    {
        if ($request->user()->role === UserRole::ADMIN) return;
        abort_unless($request->user()->role === UserRole::TEACHER, 403);
        abort_unless($plan, 403);
        $access = $plan->teacherAccesses()->where('teacher_id', $request->user()->id)->first();
        abort_unless($access || $plan->teacher_id === $request->user()->id, 403, 'Vous n’avez pas accès à cette planification.');
        if ($ability !== null) {
            $key = match ($ability) { 'groups' => 'manage_groups', 'sessions' => 'add_sessions', 'attendance' => 'record_attendance' };
            abort_unless($this->planningAccess($request, $plan)[$key], 403, 'Cette action n’est pas autorisée pour les enseignants.');
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === UserRole::ADMIN, 403);
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
