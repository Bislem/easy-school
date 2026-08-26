<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;
use App\Services\AnnualLeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class StaffController extends Controller
{
    public function __construct(private FilePondService $filePondService, private AnnualLeaveService $annualLeaveService) {}

    public function index(Request $request): RedirectResponse
    {
        return to_route('admin.users.index', $request->only(['search']));
    }

    public function show(Staff $staff): Response
    {
        Gate::authorize('view', $staff);
        $staff->load(['employeeType', 'user:id,name,email,role,can_login,is_active',
            'salaryStatements' => fn ($query) => $query->with('configuration')->latest('period_end')->limit(12),
            'salaryPayments' => fn ($query) => $query->latest('paid_at')->limit(20),
            'badges.template',
            'attendances' => fn ($query) => $query->latest('attendance_date')->limit(30),
            'documents.file', 'documents.uploader:id,name',
            'annualLeaves.creator:id,name', 'annualLeaves.approver:id,name', 'annualLeaves.events.actor:id,name',
            'leaveBalanceAdjustments.creator:id,name',
            'sickLeaves.creator:id,name', 'sickLeaves.approver:id,name', 'sickLeaves.events.actor:id,name',
            'hrRecords.creator:id,name', 'hrRecords.events.actor:id,name',
        ]);
        $staff->setAttribute('salary_configurations', $staff->salaryStatements
            ->pluck('configuration')->filter()->unique('id')->values());
        if ($staff->user_id) {
            $records = \App\Models\TeacherAttendance::with('session.group.plan.course')->where(fn ($q) => $q->where('scheduled_teacher_id', $staff->user_id)->orWhere('actual_teacher_id', $staff->user_id))->latest()->get();
            $planned = \App\Models\TrainingSession::where('teacher_id', $staff->user_id)->count();
            $plannedMinutes = \App\Models\TrainingSession::where('teacher_id', $staff->user_id)->get()->sum(fn ($s) => $s->starts_at->diffInMinutes($s->ends_at));
            $staff->setAttribute('teaching_attendance', $records);
            $staff->setAttribute('teaching_stats', ['planned' => $planned, 'completed' => \App\Models\TrainingSession::where('teacher_id', $staff->user_id)->where('status', 'completed')->count(), 'absent' => $records->where('scheduled_teacher_id', $staff->user_id)->where('status', 'absent')->count(), 'late' => $records->where('status', 'late')->count(), 'replacements' => $records->where('actual_teacher_id', $staff->user_id)->where('scheduled_teacher_id', '!=', $staff->user_id)->count(), 'planned_hours' => round($plannedMinutes / 60, 2), 'worked_hours' => round($records->where('actual_teacher_id', $staff->user_id)->whereNotNull('validated_at')->sum('worked_minutes') / 60, 2)]);
        }

        $sickLeaves = $staff->sickLeaves;
        $sickLeaveSummary = ['total_days' => (int) $sickLeaves->whereIn('status', ['approved', 'taken'])->sum('days'), 'current_year_days' => (int) $sickLeaves->whereIn('status', ['approved', 'taken'])->filter(fn ($leave) => $leave->starts_at->year === now()->year)->sum('days'), 'pending' => $sickLeaves->where('status', 'pending')->count(), 'missing_certificates' => $sickLeaves->whereIn('status', ['pending', 'approved'])->where('certificate_received', false)->count()];

        $timeline = collect()
            ->merge($staff->annualLeaves->map(fn ($item) => ['date' => $item->created_at, 'category' => 'Congé annuel', 'title' => "{$item->days} jour(s) · {$item->status}", 'detail' => $item->reason]))
            ->merge($staff->sickLeaves->map(fn ($item) => ['date' => $item->created_at, 'category' => 'Congé maladie', 'title' => "{$item->days} jour(s) · {$item->status}", 'detail' => $item->certificate_reference]))
            ->merge($staff->hrRecords->map(fn ($item) => ['date' => $item->created_at, 'category' => config("hr.record_categories.{$item->category}.label"), 'title' => $item->title, 'detail' => $item->status]))
            ->merge($staff->documents->map(fn ($item) => ['date' => $item->created_at, 'category' => 'Document', 'title' => $item->title ?: config("hr.document_types.{$item->type}"), 'detail' => $item->reference]))
            ->sortByDesc('date')->values()->take(100);

        return Inertia::render('Admin/Staff/Show', ['employee' => $staff, 'documentTypes' => config('hr.document_types'), 'annualLeaveSummary' => $this->annualLeaveService->summary($staff), 'sickLeaveSummary' => $sickLeaveSummary, 'hrRecordCategories' => config('hr.record_categories'), 'employeeTimeline' => $timeline]);
    }

    public function storeDocuments(Request $request, Staff $staff): RedirectResponse
    {
        Gate::authorize('update', $staff);
        $validated = $request->validate([
            'temp_folders' => ['required', 'array', 'min:1'], 'temp_folders.*' => ['required', 'string'],
            'type' => ['required', Rule::in(array_keys(config('hr.document_types')))],
            'title' => ['nullable', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:150'],
            'issued_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $order = $staff->files()->where('collection', 'hr_documents')->count();
        foreach ($validated['temp_folders'] as $index => $folder) {
            $file = $this->filePondService->moveTempFileToModel($staff, $folder, 'hr_documents', $order + $index);
            if ($file) {
                $staff->documents()->create([
                    ...collect($validated)->except('temp_folders')->all(),
                    'file_id' => $file->id, 'uploaded_by' => $request->user()->id,
                ]);
            }
        }

        return back()->with('success', count($validated['temp_folders']).' document(s) ajouté(s).');
    }

    public function destroyDocument(Staff $staff, EmployeeDocument $document): RedirectResponse
    {
        Gate::authorize('update', $staff);
        abort_unless($document->staff_id === $staff->id, 404);
        $file = $document->file;
        $document->delete();
        $file?->delete();

        return back()->with('success', 'Document supprimé.');
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
            'email' => ['required', 'email', 'max:255', Rule::unique('staff')->ignore($staff), Rule::unique('users')->ignore($staff?->user_id)],
            'address' => ['nullable', 'string', 'max:1000'], 'birth_date' => ['nullable', 'date', 'before:today'],
            'hire_date' => ['nullable', 'date'], 'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])], 'place_of_birth' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'], 'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'social_security_number' => ['nullable', 'string', 'max:100', Rule::unique('staff')->ignore($staff)],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'], 'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'], 'bank_account' => ['nullable', 'string', 'max:150'],
            'leave_opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'leave_balance_as_of' => ['nullable', 'required_with:leave_opening_balance', 'date', 'after_or_equal:hire_date'],
            'leave_balance_note' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'], 'photo' => ['nullable', 'image', 'max:5120'],
            'identification_type' => ['nullable', 'string', 'max:100'], 'identification_number' => ['nullable', 'string', 'max:150'],
            'identification_expires_at' => ['nullable', 'date'], 'identification_notes' => ['nullable', 'string', 'max:2000'],
            'can_login' => ['required', 'boolean'],
            'can_view_student_folders' => ['sometimes', 'boolean'],
            'password' => [Rule::requiredIf($request->boolean('can_login') && ! $staff?->user_id), 'nullable', 'confirmed', Password::defaults()],
        ]);
    }

    private function persist(Request $request, Staff $staff, array $data): Staff
    {
        $previousBalance = $staff->leave_opening_balance;
        $previousAsOf = $staff->leave_balance_as_of?->format('Y-m-d');
        $type = EmployeeType::findOrFail($data['employee_type_id']);
        $canLogin = (bool) $data['can_login'];
        unset($data['photo'], $data['password'], $data['password_confirmation'], $data['can_login']);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('staff', 'public');
        }

        $user = $staff->user;
        $payload = ['name' => trim($data['first_name'].' '.$data['last_name']), 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'] ?? null, 'role' => $type->is_teacher ? UserRole::TEACHER : UserRole::EMPLOYEE,
            'job_title' => $type->name, 'can_login' => $canLogin, 'is_active' => $data['employment_status'] === 'active'];
        if (filled($request->input('password'))) {
            $payload['password'] = $request->input('password');
        }
        $user ? $user->update($payload) : $user = User::create($payload + ['email_verified_at' => now(), 'password' => str()->password(32)]);
        $data['user_id'] = $user->id;
        $staff->fill($data)->save();

        if ($staff->wasChanged(['leave_opening_balance', 'leave_balance_as_of'])) {
            $staff->leaveBalanceAdjustments()->create([
                'previous_balance' => $previousBalance, 'previous_as_of' => $previousAsOf,
                'new_balance' => $staff->leave_opening_balance, 'new_as_of' => $staff->leave_balance_as_of,
                'reason' => $staff->leave_balance_note, 'created_by' => $request->user()->id,
            ]);
        }

        return $staff;
    }
}
