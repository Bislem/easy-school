<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EnrollmentForm;
use App\Models\FinancialAccount;
use App\Models\FinancialInstallment;
use App\Models\FinancialTransaction;
use App\Models\Formation;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use App\Services\PaymentsOverviewQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PaymentsOverviewController extends Controller
{
    public function index(Request $request, PaymentsOverviewQuery $overview): Response
    {
        $filters = $this->filters($request);
        $base = $overview->accounts($filters);
        $totals = $this->summaries($base);

        $transactions = FinancialTransaction::query()
            ->whereHas('account', fn (Builder $query) => $overview->apply($query, $filters));
        $overview->dates($transactions, 'transaction_date', $filters);

        $monthly = (clone $transactions)->selectRaw("substr(transaction_date, 1, 7) as period, SUM(CASE WHEN type = 'payment' THEN amount WHEN type IN ('refund', 'reversal') THEN -ABS(amount) ELSE 0 END) as total")
            ->groupBy('period')->orderBy('period')->limit(18)->get();
        $daily = (clone $transactions)->selectRaw("transaction_date as period, SUM(CASE WHEN type = 'payment' THEN amount WHEN type IN ('refund', 'reversal') THEN -ABS(amount) ELSE 0 END) as total")
            ->groupBy('transaction_date')->orderByDesc('transaction_date')->limit(31)->get()->reverse()->values();
        $methods = (clone $transactions)->where('type', 'payment')->whereNotNull('payment_method')
            ->selectRaw('payment_method, SUM(amount) as total')->groupBy('payment_method')->orderByDesc('total')->get();

        $recent = (clone $transactions)->with(['account.student:id,first_name,last_name', 'recorder:id,name',
            'account.accountable' => fn (MorphTo $morph) => $morph->morphWith([
                \App\Models\StudentAcademicEnrollment::class => ['academicYear:id,name', 'level:id,name', 'group:id,name'],
                \App\Models\CourseEnrollment::class => ['form.course:id,title', 'form:id,course_id,title', 'trainingPlanGroup:id,name'],
            ]),
        ])->latest('transaction_date')->latest('id')->limit(15)->get()->map(fn (FinancialTransaction $transaction) => $this->transactionRow($transaction));

        $outstanding = FinancialInstallment::query()
            ->select('financial_installments.*')
            ->selectSub(fn ($query) => $query->from('financial_allocations')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('financial_installment_id', 'financial_installments.id'), 'allocated_total')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('account', fn (Builder $query) => $overview->apply($query, $filters))
            ->with(['account.student:id,first_name,last_name',
                'account.accountable' => fn (MorphTo $morph) => $morph->morphWith([
                    \App\Models\StudentAcademicEnrollment::class => ['academicYear:id,name', 'level:id,name', 'group:id,name'],
                    \App\Models\CourseEnrollment::class => ['form.course:id,title', 'form:id,course_id,title', 'trainingPlanGroup:id,name'],
                ]),
            ])
            ->when($filters['outstanding_sort'] ?? null, fn (Builder $query, $sort) => match ($sort) {
                'remaining_desc' => $query->orderByRaw('(financial_installments.amount - (SELECT COALESCE(SUM(financial_allocations.amount), 0) FROM financial_allocations WHERE financial_allocations.financial_installment_id = financial_installments.id)) DESC'),
                'student' => $query->orderBy('financial_account_id'),
                default => $query->orderBy('due_date'),
            }, fn (Builder $query) => $query->orderBy('due_date'))
            ->paginate(15, ['*'], 'outstanding_page')->withQueryString();
        $outstanding->getCollection()->transform(fn (FinancialInstallment $installment) => $this->installmentRow($installment));

        return Inertia::render('Admin/PaymentsOverview/Index', [
            'filters' => $filters,
            'totals' => $totals,
            'charts' => ['monthly' => $monthly, 'daily' => $daily, 'methods' => $methods],
            'recentTransactions' => $recent,
            'outstanding' => $outstanding,
            'options' => $this->options(),
        ]);
    }

    public function export(Request $request, PaymentsOverviewQuery $overview): StreamedResponse
    {
        $filters = $this->filters($request);
        $accounts = $overview->accounts($filters)->with(['student',
            'accountable' => fn (MorphTo $morph) => $morph->morphWith([
                \App\Models\StudentAcademicEnrollment::class => ['academicYear', 'level', 'group'],
                \App\Models\CourseEnrollment::class => ['form.course', 'form', 'trainingPlanGroup'],
            ]),
        ])->lazyById(500);

        return response()->streamDownload(function () use ($accounts): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Source', 'Étudiant', 'Contexte', 'Attendu', 'Encaissé', 'Restant', 'Statut']);
            foreach ($accounts as $account) {
                fputcsv($output, [$account->domain, $account->student?->full_name, $this->accountContext($account), $account->expected_total, $account->paid_total, $account->balance, $account->status]);
            }
            fclose($output);
        }, 'payments-overview.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'site_id' => ['nullable', 'integer'], 'academic_year_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'source' => ['nullable', Rule::in(['all', 'school', 'formation'])], 'level_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'string', 'regex:/^(school|formation):[0-9]+$/'], 'formation_id' => ['nullable', 'integer'], 'session_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'], 'status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid', 'overdue'])],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)], 'outstanding_sort' => ['nullable', Rule::in(['due_date', 'remaining_desc', 'student'])],
            'outstanding_page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function summaries(Builder $base): array
    {
        $rows = (clone $base)->selectRaw("domain, SUM(expected_total) expected, SUM(paid_total) collected, SUM(balance) outstanding, SUM(CASE WHEN status = 'overdue' THEN balance ELSE 0 END) overdue")->groupBy('domain')->get()->keyBy('domain');
        $empty = ['expected' => 0.0, 'collected' => 0.0, 'outstanding' => 0.0, 'overdue' => 0.0];
        $result = [];
        foreach (['school', 'formation'] as $domain) {
            $row = $rows->get($domain);
            $result[$domain] = $row ? collect($empty)->mapWithKeys(fn ($value, $key) => [$key => (float) $row->{$key}])->all() : $empty;
            $result[$domain]['overdue'] = (float) FinancialInstallment::query()
                ->whereIn('financial_account_id', (clone $base)->where('domain', $domain)->select('id'))
                ->whereDate('due_date', '<', today())
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->selectRaw('COALESCE(SUM(amount - (SELECT COALESCE(SUM(financial_allocations.amount), 0) FROM financial_allocations WHERE financial_allocations.financial_installment_id = financial_installments.id)), 0) AS overdue_total')
                ->value('overdue_total');
        }
        $result['combined'] = collect($empty)->mapWithKeys(fn ($value, $key) => [$key => $result['school'][$key] + $result['formation'][$key]])->all();
        $result['combined']['collection_rate'] = $result['combined']['expected'] > 0 ? round($result['combined']['collected'] / $result['combined']['expected'] * 100, 1) : 0;

        return $result;
    }

    private function transactionRow(FinancialTransaction $transaction): array
    {
        return ['id' => $transaction->id, 'date' => $transaction->transaction_date, 'student' => $transaction->account->student?->full_name, 'source' => $transaction->account->domain, 'context' => $this->accountContext($transaction->account), 'amount' => (float) $transaction->amount, 'type' => $transaction->type, 'payment_method' => $transaction->payment_method?->value, 'recorded_by' => $transaction->recorder?->name, 'url' => $this->accountUrl($transaction->account)];
    }

    private function installmentRow(FinancialInstallment $installment): array
    {
        $account = $installment->account;

        return ['id' => $installment->id, 'student' => $account->student?->full_name, 'source' => $account->domain, 'context' => $this->accountContext($account), 'label' => $installment->label, 'due_date' => $installment->due_date, 'amount' => (float) $installment->amount, 'remaining' => max(0, (float) $installment->amount - (float) ($installment->allocated_total ?? 0)), 'status' => $installment->status, 'url' => $this->accountUrl($account)];
    }

    private function accountContext(FinancialAccount $account): string
    {
        $owner = $account->accountable;

        return $account->domain === 'school'
            ? collect([$owner?->level?->name, $owner?->group?->name, $owner?->academicYear?->name])->filter()->join(' / ')
            : collect([$owner?->form?->course?->title, $owner?->form?->title, $owner?->trainingPlanGroup?->name])->filter()->join(' / ');
    }

    private function accountUrl(FinancialAccount $account): string
    {
        return $account->domain === 'school' ? route('admin.school-payments.accounts.show', $account) : route('admin.formation-payments.accounts.show', $account);
    }

    private function options(): array
    {
        return [
            'sites' => SchoolSite::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'academicYears' => AcademicYear::latest('start_date')->get(['id', 'name']),
            'levels' => SchoolLevel::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'schoolGroups' => SchoolGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'school_level_id', 'academic_year_id']),
            'formationGroups' => TrainingPlanGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'training_plan_id']),
            'formations' => Formation::where('is_active', true)->orderBy('title')->get(['id', 'title']),
            'sessions' => EnrollmentForm::orderByDesc('start_date')->get(['id', 'course_id', 'title']),
            'students' => Student::whereHas('financialAccounts')->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()]),
        ];
    }
}
