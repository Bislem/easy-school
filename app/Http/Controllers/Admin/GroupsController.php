<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Classroom;
use App\Models\SchoolCycle;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use App\Models\User;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GroupsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'cycle_id' => ['nullable', 'integer'], 'level_id' => ['nullable', 'integer'], 'academic_period_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $groups = TrainingPlanGroup::query()
            ->with(['level.cycle', 'academicPeriod', 'classroom:id,name,code,capacity,is_active,is_available', 'principalTeacher:id,name,email', 'teachers:id,name,email'])
            ->withCount(['students', 'timetableSessions'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($filters['cycle_id'] ?? null, fn ($q, $id) => $q->whereHas('level', fn ($q) => $q->where('school_cycle_id', $id)))
            ->when($filters['level_id'] ?? null, fn ($q, $id) => $q->where('school_level_id', $id))
            ->when($filters['academic_period_id'] ?? null, fn ($q, $id) => $q->where('academic_period_id', $id))
            ->when(isset($filters['status']) && $filters['status'] !== null, fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')->paginate(18)->withQueryString();

        return Inertia::render('Admin/Groups/Index', [
            'groups' => $groups,
            'cycles' => SchoolCycle::with(['levels' => fn ($q) => $q->orderBy('sort_order')])->orderBy('sort_order')->get(),
            'periods' => AcademicPeriod::orderByDesc('starts_on')->get(),
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name', 'code', 'capacity', 'is_active', 'is_available']),
            'teachers' => User::where('role', UserRole::TEACHER)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'students' => Student::with('group:id,name')->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'school_level', 'training_plan_group_id']),
            'filters' => $filters,
        ]);
    }

    public function show(TrainingPlanGroup $group): array
    {
        return $group->load(['level.cycle', 'academicPeriod', 'classroom', 'principalTeacher:id,name,email', 'teachers:id,name,email', 'students:id,first_name,last_name,email,school_level,is_active'])->toArray();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $teachers = $data['teacher_ids'];
            unset($data['teacher_ids']);
            $data['group_number'] = ((int) TrainingPlanGroup::max('group_number')) + 1;
            $group = TrainingPlanGroup::create($data);
            $group->teachers()->sync($teachers);
        });

        return back()->with('success', 'Groupe créé avec succès.');
    }

    public function update(Request $request, TrainingPlanGroup $group): RedirectResponse
    {
        $data = $this->validated($request, $group);
        DB::transaction(function () use ($data, $group) {
            $teachers = $data['teacher_ids'];
            unset($data['teacher_ids']);
            $group->update($data);
            $group->teachers()->sync($teachers);
        });

        return back()->with('success', 'Groupe mis à jour.');
    }

    public function destroy(TrainingPlanGroup $group): RedirectResponse
    {
        if ($group->students()->exists() || $group->enrollments()->exists() || $group->sessions()->exists() || $group->timetableSessions()->exists()) {
            throw ValidationException::withMessages(['group' => 'Ce groupe contient des élèves ou des séances. Désactivez-le au lieu de le supprimer.']);
        }
        $group->delete();

        return back()->with('success', 'Groupe supprimé.');
    }

    public function assignStudents(Request $request, TrainingPlanGroup $group): RedirectResponse
    {
        $data = $request->validate(['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['integer', 'distinct', TenantRule::exists('students')], 'move_existing' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($data, $group) {
            $locked = TrainingPlanGroup::lockForUpdate()->findOrFail($group->id);
            $students = Student::whereIn('id', $data['student_ids'])->lockForUpdate()->get();
            if (! ($data['move_existing'] ?? false) && $students->whereNotNull('training_plan_group_id')->where('training_plan_group_id', '!=', $locked->id)->isNotEmpty()) {
                throw ValidationException::withMessages(['student_ids' => 'Un ou plusieurs élèves appartiennent déjà à un autre groupe. Confirmez leur déplacement.']);
            }
            $newCount = $students->where('training_plan_group_id', '!=', $locked->id)->count();
            if ($locked->capacity && $locked->students()->count() + $newCount > $locked->capacity) {
                throw ValidationException::withMessages(['student_ids' => 'La capacité maximale du groupe serait dépassée.']);
            }
            Student::whereIn('id', $students->pluck('id'))->update(['training_plan_group_id' => $locked->id]);
        });

        return back()->with('success', 'Élèves affectés au groupe.');
    }

    public function removeStudent(TrainingPlanGroup $group, Student $student): RedirectResponse
    {
        abort_unless((int) $student->training_plan_group_id === (int) $group->id, 404);
        $student->update(['training_plan_group_id' => null]);

        return back()->with('success', 'Élève retiré du groupe.');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $data = $this->validatedLevel($request);
        $data['sort_order'] = ((int) SchoolLevel::where('school_cycle_id', $data['school_cycle_id'])->max('sort_order')) + 1;
        SchoolLevel::create($data);

        return back()->with('success', 'Niveau ajouté.');
    }

    public function updateLevel(Request $request, SchoolLevel $level): RedirectResponse
    {
        $data = $this->validatedLevel($request, $level);
        if ((int) $data['school_cycle_id'] !== (int) $level->school_cycle_id) {
            $data['sort_order'] = ((int) SchoolLevel::where('school_cycle_id', $data['school_cycle_id'])->max('sort_order')) + 1;
        }
        $level->update($data);

        return back()->with('success', 'Niveau mis à jour.');
    }

    public function destroyLevel(SchoolLevel $level): RedirectResponse
    {
        if ($level->groups()->exists() || $level->subjects()->exists()) {
            throw ValidationException::withMessages(['level' => 'Ce niveau est utilisé par des groupes ou des matières. Désactivez-le.']);
        }
        $level->delete();

        return back()->with('success', 'Niveau supprimé.');
    }

    private function validated(Request $request, ?TrainingPlanGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:50', TenantRule::unique('training_plan_groups', 'code')->ignore($group)],
            'school_level_id' => ['required', TenantRule::exists('school_levels')->where('is_active', true)], 'academic_period_id' => ['required', TenantRule::exists('academic_periods')],
            'classroom_id' => ['nullable', TenantRule::exists('classrooms')], 'capacity' => ['required', 'integer', 'between:1,1000'], 'is_active' => ['required', 'boolean'],
            'teacher_ids' => ['present', 'array'], 'teacher_ids.*' => ['integer', 'distinct', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)->where('is_active', true)],
            'principal_teacher_id' => ['nullable', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)->where('is_active', true)],
        ]);
        if ($data['principal_teacher_id'] && ! in_array((int) $data['principal_teacher_id'], array_map('intval', $data['teacher_ids']), true)) {
            $data['teacher_ids'][] = (int) $data['principal_teacher_id'];
        }
        if ($data['classroom_id']) {
            $room = Classroom::findOrFail($data['classroom_id']);
            if (! $room->is_active || ! $room->is_available) {
                throw ValidationException::withMessages(['classroom_id' => 'La salle par défaut est désactivée ou indisponible.']);
            }
            if ($data['capacity'] > $room->capacity) {
                throw ValidationException::withMessages(['capacity' => "La capacité dépasse celle de la salle ({$room->capacity})."]);
            }
        }

        return $data;
    }

    private function validatedLevel(Request $request, ?SchoolLevel $level = null): array
    {
        $specialization = $request->string('specialization')->trim()->toString() ?: null;
        $uniqueCode = TenantRule::unique('school_levels', 'code');
        if ($specialization === null) {
            $uniqueCode->whereNull('specialization');
        } else {
            $uniqueCode->where('specialization', $specialization);
        }
        if ($level) {
            $uniqueCode->ignore($level);
        }

        $data = $request->validate([
            'school_cycle_id' => ['required', TenantRule::exists('school_cycles')],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', $uniqueCode],
            'specialization' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['specialization'] = $specialization;

        return $data;
    }
}
