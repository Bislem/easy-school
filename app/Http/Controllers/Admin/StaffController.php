<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Staff::class);
        $staff = Staff::query()->with(['employeeType:id,name,slug,is_teacher', 'user:id,email,role,can_login,is_active'])
            ->when($request->string('search')->trim()->toString(), fn ($query, string $search) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")))
            ->when($request->filled('type'), fn ($query) => $query->where('employee_type_id', $request->integer('type')))
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('employment_status', $status))
            ->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff, 'employeeTypes' => $this->types(),
            'filters' => $request->only(['search', 'type', 'status']),
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Staff $staff): Response
    {
        Gate::authorize('view', $staff);
        $staff->load(['employeeType', 'user:id,name,email,role,can_login,is_active',
            'salaryConfigurations' => fn($query) => $query->latest('effective_from'),
            'salaryStatements' => fn($query) => $query->latest('period_end')->limit(12),
            'salaryPayments' => fn($query) => $query->latest('paid_at')->limit(20),
            'badges.template',
            'attendances' => fn($query) => $query->latest('attendance_date')->limit(30),
        ]);
        if($staff->user_id){$records=\App\Models\TeacherAttendance::with('session.group.plan.course')->where(fn($q)=>$q->where('scheduled_teacher_id',$staff->user_id)->orWhere('actual_teacher_id',$staff->user_id))->latest()->get();$planned=\App\Models\TrainingSession::where('teacher_id',$staff->user_id)->count();$plannedMinutes=\App\Models\TrainingSession::where('teacher_id',$staff->user_id)->get()->sum(fn($s)=>$s->starts_at->diffInMinutes($s->ends_at));$staff->setAttribute('teaching_attendance',$records);$staff->setAttribute('teaching_stats',['planned'=>$planned,'completed'=>\App\Models\TrainingSession::where('teacher_id',$staff->user_id)->where('status','completed')->count(),'absent'=>$records->where('scheduled_teacher_id',$staff->user_id)->where('status','absent')->count(),'late'=>$records->where('status','late')->count(),'replacements'=>$records->where('actual_teacher_id',$staff->user_id)->where('scheduled_teacher_id','!=',$staff->user_id)->count(),'planned_hours'=>round($plannedMinutes/60,2),'worked_hours'=>round($records->where('actual_teacher_id',$staff->user_id)->whereNotNull('validated_at')->sum('worked_minutes')/60,2)]);}
        return Inertia::render('Admin/Staff/Show', ['employee' => $staff]);
    }

    public function edit(Staff $staff): Response
    {
        Gate::authorize('update', $staff);
        $staff->load(['employeeType', 'user:id,can_login']);
        return Inertia::render('Admin/Staff/Edit', [
            'employee' => $staff, 'employeeTypes' => $this->types(), 'statuses' => $this->statuses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Staff::class);
        $data = $this->validated($request);
        $staff = DB::transaction(fn () => $this->persist($request, new Staff, $data));
        return to_route('admin.staff.show', $staff)->with('success', 'Employé créé avec succès.');
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        $data = $this->validated($request, $staff);
        DB::transaction(fn () => $this->persist($request, $staff, $data));
        return back()->with('success', 'Profil employé mis à jour.');
    }

    public function toggleActive(Staff $staff): RedirectResponse
    {
        Gate::authorize('changeStatus', $staff);
        $status = $staff->employment_status === EmploymentStatus::ACTIVE ? EmploymentStatus::INACTIVE : EmploymentStatus::ACTIVE;
        $staff->update(['employment_status' => $status]);
        $staff->user?->update(['is_active' => $status === EmploymentStatus::ACTIVE]);
        return back()->with('success', $status === EmploymentStatus::ACTIVE ? 'Employé activé.' : 'Employé désactivé.');
    }

    public function types(): array
    {
        return EmployeeType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug', 'is_teacher'])->all();
    }

    private function statuses(): array
    {
        return [['value' => 'active', 'label' => 'Actif'], ['value' => 'inactive', 'label' => 'Inactif'], ['value' => 'on_leave', 'label' => 'En congé'], ['value' => 'terminated', 'label' => 'Fin de contrat']];
    }

    private function validated(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'employee_type_id' => ['required', Rule::exists('employee_types', 'id')->where('is_active', true)],
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('staff')->ignore($staff)],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('staff')->ignore($staff), Rule::unique('users')->ignore($staff?->user_id)],
            'address' => ['nullable', 'string', 'max:1000'], 'birth_date' => ['nullable', 'date', 'before:today'],
            'hire_date' => ['nullable', 'date'], 'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'], 'photo' => ['nullable', 'image', 'max:5120'],
            'identification_type' => ['nullable', 'string', 'max:100'], 'identification_number' => ['nullable', 'string', 'max:150'],
            'identification_expires_at' => ['nullable', 'date'], 'identification_notes' => ['nullable', 'string', 'max:2000'],
            'can_login' => ['required', 'boolean'],
            'password' => [Rule::requiredIf($request->boolean('can_login') && ! $staff?->user_id), 'nullable', 'confirmed', Password::defaults()],
        ]);
    }

    private function persist(Request $request, Staff $staff, array $data): Staff
    {
        $type = EmployeeType::findOrFail($data['employee_type_id']);
        $canLogin = (bool) $data['can_login'];
        unset($data['photo'], $data['password'], $data['password_confirmation'], $data['can_login']);
        if ($request->hasFile('photo')) $data['photo_path'] = $request->file('photo')->store('staff', 'public');

        $user = $staff->user;
        if ($canLogin || $user) {
            abort_if(blank($data['email']), 422, 'Une adresse e-mail est requise pour autoriser la connexion.');
            $payload = ['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'birth_date' => $data['birth_date'] ?? null, 'role' => $type->is_teacher ? UserRole::TEACHER : UserRole::EMPLOYEE,
                'job_title' => $type->name, 'can_login' => $canLogin, 'is_active' => $data['employment_status'] === 'active'];
            if (filled($request->input('password'))) $payload['password'] = $request->input('password');
            $user ? $user->update($payload) : $user = User::create($payload + ['email_verified_at' => now()]);
            $data['user_id'] = $user->id;
        }
        $staff->fill($data)->save();
        return $staff;
    }
}
