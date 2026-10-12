<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesFinancialAccounts;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FinancialAccount;
use App\Models\SchoolFeeStructure;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\SchoolFeeGenerationService;
use App\Support\Money;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class SchoolPaymentsController extends Controller
{
    use HandlesFinancialAccounts;

    public function index(Request $request): Response
    {
        $filters = $request->validate(['academic_year_id' => ['nullable', 'integer'], 'site_id' => ['nullable', 'integer'], 'level_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer'], 'student_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid', 'overdue'])], 'search' => ['nullable', 'string', 'max:100']]);
        $yearId = (int) ($filters['academic_year_id'] ?? $request->session()->get('academic_year_id') ?? AcademicYear::where('status', 'active')->value('id'));
        $query = FinancialAccount::query()->where('domain', 'school')->where('academic_year_id', $yearId)->with([
            'student:id,first_name,last_name',
            'accountable.level:id,name,code,specialization',
            'accountable.group:id,name,code,capacity,classroom_id,school_stream_id',
            'accountable.group.classroom:id,name,code,school_site_id',
            'accountable.group.classroom.site:id,name',
            'accountable.group.stream:id,code,name_fr,name_ar',
            'installments' => fn ($q) => $q->orderBy('due_date'),
        ]);
        $query->when($filters['level_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn ($e) => $e->where('school_level_id', $id)))
            ->when($filters['group_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn ($e) => $e->where('school_group_id', $id)))
            ->when($filters['site_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn ($e) => $e->whereHas('group.classroom', fn ($c) => $c->where('school_site_id', $id))))
            ->when($filters['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('student', fn ($s) => $s->where(fn ($s) => $s->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))));
        $statsQuery = clone $query;
        $expected = (float) (clone $statsQuery)->sum('expected_total');
        $collected = (float) (clone $statsQuery)->sum('paid_total');
        $accounts = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $accounts->getCollection()->each(fn ($account) => $account->setAttribute('next_due_date', $account->installments->first(fn ($i) => in_array($i->status, ['pending', 'partial', 'overdue'], true))?->due_date));

        $levels = SchoolLevel::query()->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNotNull('specialization')
                    ->orWhereNotExists(function ($siblings): void {
                        $siblings->selectRaw('1')->from('school_levels as specialized_levels')
                            ->whereColumn('specialized_levels.tenant_id', 'school_levels.tenant_id')
                            ->whereColumn('specialized_levels.code', 'school_levels.code')
                            ->whereNotNull('specialized_levels.specialization')
                            ->where('specialized_levels.is_active', true);
                    });
            })
            ->orderBy('sort_order')->orderBy('code')->orderBy('specialization')
            ->get(['id', 'name', 'code', 'specialization']);

        return Inertia::render('Admin/SchoolPayments/Index', ['accounts' => $accounts, 'filters' => [...$filters, 'academic_year_id' => $yearId], 'stats' => ['expected' => $expected, 'collected' => $collected, 'outstanding' => (float) (clone $statsQuery)->sum('balance'), 'overdue' => (float) (clone $statsQuery)->where('status', 'overdue')->sum('balance'), 'collection_rate' => $expected > 0 ? round($collected / $expected * 100, 1) : 0, 'unpaid_students' => (clone $statsQuery)->where('balance', '>', 0)->distinct()->count('student_id')], 'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']), 'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'levels' => $levels, 'groups' => SchoolGroup::with(['level:id,name,code,specialization', 'stream:id,code,name_fr,name_ar', 'classroom:id,name,code,school_site_id', 'classroom.site:id,name'])->where('academic_year_id', $yearId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity', 'school_level_id', 'school_stream_id', 'classroom_id']), 'students' => Student::whereHas('academicEnrollments', fn ($q) => $q->where('academic_year_id', $yearId))->orderBy('first_name')->get(['id', 'first_name', 'last_name']), 'feeStructures' => SchoolFeeStructure::with(['academicYear:id,name', 'level:id,name,code,specialization', 'group:id,name,code', 'student:id,first_name,last_name', 'components', 'scheduleItems'])->where('academic_year_id', $yearId)->latest()->get(), 'methods' => $this->paymentMethods()]);
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')], 'school_level_id' => ['nullable', TenantRule::exists('school_levels')], 'school_group_id' => ['nullable', TenantRule::exists('school_groups')], 'student_id' => ['nullable', TenantRule::exists('students')], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000'], 'components' => ['required', 'array', 'min:1'], 'components.*.code' => ['required', 'string', 'max:50', 'distinct'], 'components.*.label' => ['required', 'string', 'max:150'], 'components.*.amount' => ['required', 'numeric', 'min:0'], 'schedule' => ['required', 'array', 'min:1'], 'schedule.*.label' => ['required', 'string', 'max:150'], 'schedule.*.amount' => ['required', 'numeric', 'min:0.01'], 'schedule.*.due_date' => ['required', 'date'], 'schedule.*.component_index' => ['nullable', 'integer', 'min:0']]);
        $this->validateStructureTarget($data);
        $componentsTotal = collect($data['components'])->sum(fn ($c) => Money::cents($c['amount']));
        if ($componentsTotal !== collect($data['schedule'])->sum(fn ($i) => Money::cents($i['amount']))) {
            throw ValidationException::withMessages(['schedule' => 'La somme des échéances doit être égale au total des composantes ('.Money::amount($componentsTotal).').']);
        }
        DB::transaction(function () use ($data) {
            $structure = SchoolFeeStructure::create(collect($data)->except(['components', 'schedule'])->all() + ['is_active' => true]);
            $components = collect($data['components'])->map(fn ($item, $i) => $structure->components()->create([...$item, 'is_mandatory' => true, 'sort_order' => $i]));
            foreach ($data['schedule'] as $i => $item) {
                $componentIndex = $item['component_index'] ?? null;
                $structure->scheduleItems()->create(['school_fee_component_id' => $componentIndex !== null ? $components->get($componentIndex)?->id : null, 'label' => $item['label'], 'amount' => $item['amount'], 'due_date' => $item['due_date'], 'sort_order' => $i]);
            }
        });

        return back()->with('success', 'Structure tarifaire créée.');
    }

    public function previewGeneration(Request $request, SchoolFeeGenerationService $service): array
    {
        [$structure, $levelId, $groupId] = $this->generationTarget($request);

        return $service->preview($structure, $levelId, $groupId);
    }

    public function generate(Request $request, SchoolFeeGenerationService $service): RedirectResponse
    {
        [$structure, $levelId, $groupId] = $this->generationTarget($request);
        $result = $service->generate($structure, $levelId, $groupId, $request->boolean('update_existing'));

        return back()->with('success', "{$result['created']} compte(s) créé(s), {$result['updated']} mis à jour, {$result['skipped']} ignoré(s).");
    }

    public function show(FinancialAccount $account): Response
    {
        $this->ensureDomain($account);
        $account->load(['student', 'academicYear', 'feeStructure', 'accountable.level', 'accountable.group', 'installments.allocations', 'transactions.recorder', 'transactions.reversal:id,reverses_transaction_id']);

        return Inertia::render('Admin/SchoolPayments/Show', ['account' => $account, 'methods' => $this->paymentMethods()]);
    }

    protected function domain(): string
    {
        return 'school';
    }

    /** @return array{0: SchoolFeeStructure, 1: ?int, 2: ?int} */
    private function generationTarget(Request $request): array
    {
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')], 'level_id' => ['nullable', TenantRule::exists('school_levels')], 'group_id' => ['nullable', TenantRule::exists('school_groups')], 'school_fee_structure_id' => ['required', TenantRule::exists('school_fee_structures')], 'update_existing' => ['boolean']]);
        $structure = SchoolFeeStructure::with('scheduleItems')->findOrFail($data['school_fee_structure_id']);
        if ((int) $structure->academic_year_id !== (int) $data['academic_year_id']) {
            throw ValidationException::withMessages(['school_fee_structure_id' => 'Cette structure appartient à une autre année scolaire.']);
        }
        if ($structure->school_level_id && isset($data['level_id']) && (int) $structure->school_level_id !== (int) $data['level_id']) {
            throw ValidationException::withMessages(['level_id' => 'Cette structure appartient à un autre niveau ou une autre spécialité.']);
        }
        if ($structure->school_group_id && isset($data['group_id']) && (int) $structure->school_group_id !== (int) $data['group_id']) {
            throw ValidationException::withMessages(['group_id' => 'Cette structure appartient à un autre groupe.']);
        }

        return [$structure, isset($data['level_id']) ? (int) $data['level_id'] : null, isset($data['group_id']) ? (int) $data['group_id'] : null];
    }

    private function validateStructureTarget(array $data): void
    {
        if (! empty($data['school_group_id'])) {
            $group = SchoolGroup::findOrFail($data['school_group_id']);
            if ((int) $group->academic_year_id !== (int) $data['academic_year_id'] || (! empty($data['school_level_id']) && (int) $group->school_level_id !== (int) $data['school_level_id'])) {
                throw ValidationException::withMessages(['school_group_id' => 'Ce groupe n’appartient pas à cette année ou à ce niveau.']);
            }
        }
        if (! empty($data['student_id']) && ! StudentAcademicEnrollment::where('academic_year_id', $data['academic_year_id'])->where('student_id', $data['student_id'])->exists()) {
            throw ValidationException::withMessages(['student_id' => 'Cet élève n’est pas inscrit pour cette année scolaire.']);
        }
    }
}
