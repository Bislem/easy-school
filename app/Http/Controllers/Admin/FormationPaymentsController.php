<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\Formation;
use App\Models\FormationPricingConfig;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use App\Services\FinancialAccountService;
use App\Services\FinancialReceiptService;
use App\Services\FormationBillingService;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormationPaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['formation_id' => ['nullable', 'integer'], 'session_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer'], 'student_id' => ['nullable', 'integer'], 'site_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid', 'overdue'])], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'search' => ['nullable', 'string', 'max:100']]);
        $query = FinancialAccount::where('domain', 'formation')->with(['student:id,first_name,last_name', 'accountable.form.course:id,title', 'accountable.form:id,course_id,title,start_date,end_date,classroom_id', 'accountable.trainingPlanGroup.plan:id,title,course_level_id', 'accountable.trainingPlanGroup:id,training_plan_id,name,classroom_id', 'accountable.trainingPlanGroup.classroom:id,school_site_id', 'installments' => fn ($q) => $q->orderBy('due_date')]);
        $query->when($filters['formation_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [CourseEnrollment::class], fn ($e) => $e->whereHas('form', fn ($f) => $f->where('course_id', $id))))->when($filters['session_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [CourseEnrollment::class], fn ($e) => $e->where('enrollment_form_id', $id)))->when($filters['group_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [CourseEnrollment::class], fn ($e) => $e->where('training_plan_group_id', $id)))->when($filters['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($filters['site_id'] ?? null, fn ($q, $id) => $q->whereHasMorph('accountable', [CourseEnrollment::class], fn ($e) => $e->where(fn ($e) => $e->whereHas('trainingPlanGroup.classroom', fn ($c) => $c->where('school_site_id', $id))->orWhereHas('form.classroom', fn ($c) => $c->where('school_site_id', $id)))))->when($filters['search'] ?? null, fn ($q, $s) => $q->whereHas('student', fn ($student) => $student->where(fn ($student) => $student->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"))))->when(($filters['date_from'] ?? null) || ($filters['date_to'] ?? null), fn ($q) => $q->whereHas('transactions', fn ($t) => $t->when($filters['date_from'] ?? null, fn ($t, $d) => $t->whereDate('transaction_date', '>=', $d))->when($filters['date_to'] ?? null, fn ($t, $d) => $t->whereDate('transaction_date', '<=', $d))));
        $statsQuery = clone $query;
        $expected = (float) (clone $statsQuery)->sum('expected_total');
        $collected = (float) (clone $statsQuery)->sum('paid_total');
        $accounts = $query->latest('updated_at')->paginate(20)->withQueryString();
        $accounts->getCollection()->each(fn ($a) => $a->setAttribute('next_due_date', $a->installments->first(fn ($i) => in_array($i->status, ['pending', 'partial', 'overdue'], true))?->due_date));

        return Inertia::render('Admin/FormationPayments/Index', ['accounts' => $accounts, 'filters' => $filters, 'stats' => ['expected' => $expected, 'collected' => $collected, 'remaining' => (float) (clone $statsQuery)->sum('balance'), 'overdue' => (float) (clone $statsQuery)->where('status', 'overdue')->sum('balance'), 'collection_rate' => $expected ? round($collected / $expected * 100, 1) : 0], 'formations' => Formation::where('is_active', true)->orderBy('title')->get(['id', 'title']), 'sessions' => EnrollmentForm::with('course:id,title')->orderByDesc('start_date')->get(['id', 'course_id', 'title', 'start_date', 'end_date', 'classroom_id']), 'groups' => TrainingPlanGroup::with('plan.level.course:id,title')->where('is_active', true)->orderBy('name')->get(), 'students' => Student::whereHas('enrollments')->orderBy('first_name')->get(['id', 'first_name', 'last_name']), 'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']), 'pricingConfigs' => FormationPricingConfig::with(['course:id,title', 'session:id,title', 'group:id,name', 'scheduleItems'])->latest()->get(), 'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])]);
    }

    public function storePricing(Request $request): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['required', TenantRule::exists('courses')], 'enrollment_form_id' => ['nullable', TenantRule::exists('enrollment_forms')], 'training_plan_group_id' => ['nullable', TenantRule::exists('training_plan_groups')], 'name' => ['required', 'string', 'max:150'], 'total_price' => ['required', 'numeric', 'min:0.01'], 'schedule' => ['required', 'array', 'min:1'], 'schedule.*.label' => ['required', 'string', 'max:150'], 'schedule.*.amount' => ['required', 'numeric', 'min:0.01'], 'schedule.*.due_date' => ['required', 'date']]);
        if (isset($data['enrollment_form_id']) && ! EnrollmentForm::whereKey($data['enrollment_form_id'])->where('course_id', $data['course_id'])->exists()) {
            throw ValidationException::withMessages(['enrollment_form_id' => 'La session sélectionnée ne correspond pas à cette formation.']);
        }
        if (isset($data['training_plan_group_id']) && ! TrainingPlanGroup::whereKey($data['training_plan_group_id'])->whereHas('plan', fn ($query) => $query->where('course_id', $data['course_id'])->when($data['enrollment_form_id'] ?? null, fn ($query, $sessionId) => $query->where('enrollment_form_id', $sessionId)))->exists()) {
            throw ValidationException::withMessages(['training_plan_group_id' => 'Le groupe sélectionné ne correspond pas à cette formation/session.']);
        }
        $sum = collect($data['schedule'])->sum(fn ($i) => (float) $i['amount']);
        if (abs($sum - (float) $data['total_price']) > 0.009) {
            throw ValidationException::withMessages(['schedule' => 'La somme des échéances doit correspondre au prix total.']);
        }
        DB::transaction(function () use ($data) {
            $pricing = FormationPricingConfig::create(collect($data)->except('schedule')->all() + ['is_active' => true]);
            foreach ($data['schedule'] as $i => $item) {
                $pricing->scheduleItems()->create([...$item, 'sort_order' => $i]);
            }
        });

        return back()->with('success', 'Tarification de formation enregistrée.');
    }

    public function preview(Request $request, FormationBillingService $service): array
    {
        $d = $request->validate(['formation_id' => ['nullable', TenantRule::exists('courses')], 'session_id' => ['nullable', TenantRule::exists('enrollment_forms')], 'group_id' => ['nullable', TenantRule::exists('training_plan_groups')]]);

        return $service->preview($d['formation_id'] ?? null, $d['session_id'] ?? null, $d['group_id'] ?? null);
    }

    public function generate(Request $request, FormationBillingService $service): RedirectResponse
    {
        $d = $request->validate(['formation_pricing_config_id' => ['required', TenantRule::exists('formation_pricing_configs')], 'session_id' => ['nullable', TenantRule::exists('enrollment_forms')], 'group_id' => ['nullable', TenantRule::exists('training_plan_groups')], 'update_existing' => ['boolean']]);
        $pricing = FormationPricingConfig::with('scheduleItems')->findOrFail($d['formation_pricing_config_id']);
        if (isset($d['session_id']) && ! EnrollmentForm::whereKey($d['session_id'])->where('course_id', $pricing->course_id)->exists()) {
            throw ValidationException::withMessages(['session_id' => 'La session sélectionnée ne correspond pas à cette tarification.']);
        }
        if (isset($d['group_id']) && ! TrainingPlanGroup::whereKey($d['group_id'])->whereHas('plan', fn ($query) => $query->where('course_id', $pricing->course_id)->when($d['session_id'] ?? null, fn ($query, $sessionId) => $query->where('enrollment_form_id', $sessionId)))->exists()) {
            throw ValidationException::withMessages(['group_id' => 'Le groupe sélectionné ne correspond pas à cette tarification.']);
        }
        $r = $service->generate($pricing, $d['session_id'] ?? null, $d['group_id'] ?? null, (bool) ($d['update_existing'] ?? false));

        return back()->with('success', "{$r['created']} compte(s) créé(s), {$r['updated']} mis à jour, {$r['skipped']} ignoré(s).");
    }

    public function show(FinancialAccount $account): Response
    {
        abort_unless($account->domain === 'formation', 404);
        $account->load(['student', 'accountable.form.course', 'accountable.trainingPlanGroup.plan.level.course', 'accountable.trainingPlanGroup', 'installments.allocations', 'transactions.recorder']);

        return Inertia::render('Admin/FormationPayments/Show', ['account' => $account, 'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])]);
    }

    public function pay(Request $request, FinancialAccount $account, FinancialAccountService $service): RedirectResponse
    {
        abort_unless($account->domain === 'formation', 404);
        $d = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'transaction_date' => ['required', 'date'], 'payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'reference' => ['nullable', 'string', 'max:100', 'unique:financial_transactions,reference'], 'external_reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->recordPayment($account, $d, $request->user()->id);

        return back()->with('success', 'Paiement de formation enregistré.');
    }

    public function movement(Request $request, FinancialAccount $account, FinancialAccountService $service): RedirectResponse
    {
        abort_unless($account->domain === 'formation', 404);
        $d = $request->validate(['type' => ['required', Rule::in(['discount', 'scholarship', 'adjustment', 'refund'])], 'amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:1000']]);
        abort_if($d['type'] === 'refund' && ! $request->user()->can('payments.refund'), 403);
        $service->recordMovement($account, $d['type'], (float) $d['amount'], $d['reason'], $request->user()->id);

        return back()->with('success', 'Mouvement financier enregistré.');
    }

    public function receipt(FinancialTransaction $transaction, FinancialReceiptService $receipts): HttpResponse
    {
        abort_unless($transaction->account()->where('domain', 'formation')->exists(), 404);

        return $receipts->download($transaction);
    }

    public function export(): StreamedResponse
    {
        $accounts = FinancialAccount::where('domain', 'formation')->with(['student', 'accountable.form.course', 'accountable.form', 'accountable.trainingPlanGroup'])->get();

        return response()->streamDownload(function () use ($accounts) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Élève', 'Formation', 'Session', 'Groupe', 'Attendu', 'Payé', 'Restant', 'Statut']);
            foreach ($accounts as $a) {
                fputcsv($out, [$a->student?->full_name, $a->accountable?->form?->course?->title, $a->accountable?->form?->title, $a->accountable?->trainingPlanGroup?->name, $a->expected_total, $a->paid_total, $a->balance, $a->status]);
            }
            fclose($out);
        }, 'formation-payments.csv', ['Content-Type' => 'text/csv']);
    }
}
