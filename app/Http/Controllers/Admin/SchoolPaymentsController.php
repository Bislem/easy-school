<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\SchoolFeeStructure;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Services\FinancialAccountService;
use App\Services\FinancialReceiptService;
use App\Services\SchoolFeeGenerationService;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class SchoolPaymentsController extends Controller
{
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

        return Inertia::render('Admin/SchoolPayments/Index', ['accounts' => $accounts, 'filters' => [...$filters, 'academic_year_id' => $yearId], 'stats' => ['expected' => $expected, 'collected' => $collected, 'outstanding' => (float) (clone $statsQuery)->sum('balance'), 'overdue' => (float) (clone $statsQuery)->where('status', 'overdue')->sum('balance'), 'collection_rate' => $expected > 0 ? round($collected / $expected * 100, 1) : 0, 'unpaid_students' => (clone $statsQuery)->where('balance', '>', 0)->distinct()->count('student_id')], 'academicYears' => AcademicYear::orderByDesc('start_date')->get(['id', 'name']), 'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'levels' => $levels, 'groups' => SchoolGroup::with(['level:id,name,code,specialization', 'stream:id,code,name_fr,name_ar', 'classroom:id,name,code,school_site_id', 'classroom.site:id,name'])->where('academic_year_id', $yearId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'capacity', 'school_level_id', 'school_stream_id', 'classroom_id']), 'students' => Student::whereHas('academicEnrollments', fn ($q) => $q->where('academic_year_id', $yearId))->orderBy('first_name')->get(['id', 'first_name', 'last_name']), 'feeStructures' => SchoolFeeStructure::with(['academicYear:id,name', 'level:id,name,code,specialization', 'group:id,name,code', 'student:id,first_name,last_name', 'components', 'scheduleItems'])->where('academic_year_id', $yearId)->latest()->get(), 'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])]);
    }

    public function storeStructure(Request $request): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')], 'school_level_id' => ['nullable', TenantRule::exists('school_levels')], 'school_group_id' => ['nullable', TenantRule::exists('school_groups')], 'student_id' => ['nullable', TenantRule::exists('students')], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000'], 'components' => ['required', 'array', 'min:1'], 'components.*.code' => ['required', 'string', 'max:50'], 'components.*.label' => ['required', 'string', 'max:150'], 'components.*.amount' => ['required', 'numeric', 'min:0'], 'components.*.is_mandatory' => ['required', 'boolean'], 'schedule' => ['required', 'array', 'min:1'], 'schedule.*.label' => ['required', 'string', 'max:150'], 'schedule.*.amount' => ['required', 'numeric', 'min:0.01'], 'schedule.*.due_date' => ['required', 'date'], 'schedule.*.component_index' => ['nullable', 'integer', 'min:0']]);
        DB::transaction(function () use ($data) {
            $structure = SchoolFeeStructure::create(collect($data)->except(['components', 'schedule'])->all() + ['is_active' => true]);
            $components = collect($data['components'])->map(fn ($item, $i) => $structure->components()->create([...$item, 'sort_order' => $i]));
            foreach ($data['schedule'] as $i => $item) {
                $componentIndex = $item['component_index'] ?? null;
                $structure->scheduleItems()->create(['school_fee_component_id' => $componentIndex !== null ? $components->get($componentIndex)?->id : null, 'label' => $item['label'], 'amount' => $item['amount'], 'due_date' => $item['due_date'], 'sort_order' => $i]);
            }
        });

        return back()->with('success', 'Structure tarifaire créée.');
    }

    public function previewGeneration(Request $request, SchoolFeeGenerationService $service): array
    {
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')], 'level_id' => ['nullable', TenantRule::exists('school_levels')], 'group_id' => ['nullable', TenantRule::exists('school_groups')], 'school_fee_structure_id' => ['nullable', TenantRule::exists('school_fee_structures')]]);
        if ($structureId = $data['school_fee_structure_id'] ?? null) {
            $structure = SchoolFeeStructure::findOrFail($structureId);
            $data['level_id'] = $structure->school_level_id ?: ($data['level_id'] ?? null);
            $data['group_id'] = $structure->school_group_id ?: ($data['group_id'] ?? null);
        }

        return $service->preview((int) $data['academic_year_id'], $data['level_id'] ?? null, $data['group_id'] ?? null);
    }

    public function generate(Request $request, SchoolFeeGenerationService $service): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')], 'level_id' => ['nullable', TenantRule::exists('school_levels')], 'group_id' => ['nullable', TenantRule::exists('school_groups')], 'school_fee_structure_id' => ['required', TenantRule::exists('school_fee_structures')], 'update_existing' => ['boolean'], 'optional_component_ids' => ['array'], 'optional_component_ids.*' => [TenantRule::exists('school_fee_components')]]);
        $structure = SchoolFeeStructure::with(['components', 'scheduleItems'])->findOrFail($data['school_fee_structure_id']);
        abort_unless((int) $structure->academic_year_id === (int) $data['academic_year_id'], 422);
        abort_if($structure->school_level_id && isset($data['level_id']) && (int) $structure->school_level_id !== (int) $data['level_id'], 422, 'Cette structure appartient à un autre niveau ou une autre spécialité.');
        abort_if($structure->school_group_id && isset($data['group_id']) && (int) $structure->school_group_id !== (int) $data['group_id'], 422, 'Cette structure appartient à un autre groupe.');
        $data['level_id'] = $structure->school_level_id ?: ($data['level_id'] ?? null);
        $data['group_id'] = $structure->school_group_id ?: ($data['group_id'] ?? null);
        $result = $service->generate($structure, (int) $data['academic_year_id'], $data['level_id'] ?? null, $data['group_id'] ?? null, (bool) ($data['update_existing'] ?? false), array_map('intval', $data['optional_component_ids'] ?? []));

        return back()->with('success', "{$result['created']} compte(s) créé(s), {$result['updated']} mis à jour, {$result['skipped']} ignoré(s).");
    }

    public function show(FinancialAccount $account): Response
    {
        abort_unless($account->domain === 'school', 404);
        $account->load(['student', 'academicYear', 'feeStructure', 'accountable.level', 'accountable.group', 'installments.allocations', 'transactions.recorder']);

        return Inertia::render('Admin/SchoolPayments/Show', ['account' => $account, 'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])]);
    }

    public function pay(Request $request, FinancialAccount $account, FinancialAccountService $service): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'transaction_date' => ['required', 'date'], 'payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'reference' => ['nullable', 'string', 'max:100', 'unique:financial_transactions,reference'], 'external_reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->recordPayment($account, $data, $request->user()->id);

        return back()->with('success', 'Paiement enregistré et alloué.');
    }

    public function movement(Request $request, FinancialAccount $account, FinancialAccountService $service): RedirectResponse
    {
        $data = $request->validate(['type' => ['required', Rule::in(['discount', 'scholarship', 'adjustment', 'refund'])], 'amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:1000']]);
        abort_if($data['type'] === 'refund' && ! $request->user()->can('payments.refund'), 403);
        $service->recordMovement($account, $data['type'], (float) $data['amount'], $data['reason'], $request->user()->id);

        return back()->with('success', 'Mouvement financier enregistré.');
    }

    public function receipt(FinancialTransaction $transaction, FinancialReceiptService $receipts): HttpResponse
    {
        return $receipts->download($transaction);
    }
}
