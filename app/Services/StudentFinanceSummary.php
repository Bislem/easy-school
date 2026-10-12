<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\FinancialAccount;
use App\Models\FinancialInstallment;
use App\Models\FinancialTransaction;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Read model of a student's billing accounts for admin, portal and parent screens. */
final class StudentFinanceSummary
{
    /** @return array{accounts: list<array<string, mixed>>, totals: array<string, float>} */
    public function forStudent(Student $student): array
    {
        $accounts = FinancialAccount::where('student_id', $student->id)
            ->with([
                'accountable' => fn (MorphTo $morph) => $morph->morphWith([
                    StudentAcademicEnrollment::class => ['academicYear:id,name', 'level:id,name', 'group:id,name'],
                    CourseEnrollment::class => ['form.course:id,title', 'form:id,course_id,title', 'trainingPlanGroup:id,name,training_plan_id', 'trainingPlanGroup.plan.level.course:id,title'],
                ]),
                'installments' => fn ($q) => $q->reorder()->withSum('allocations', 'amount')->orderBy('due_date')->orderBy('sort_order'),
                'transactions' => fn ($q) => $q->where('type', 'payment')->with('reversal:id,reverses_transaction_id,transaction_date,reason'),
            ])
            ->orderByDesc('created_at')->get()
            ->map(fn (FinancialAccount $account) => $this->account($account))->values()->all();

        return ['accounts' => $accounts, 'totals' => [
            'expected' => collect($accounts)->sum('expected'),
            'paid' => collect($accounts)->sum('paid'),
            'balance' => collect($accounts)->sum('balance'),
            'overdue' => collect($accounts)->sum('overdue'),
        ]];
    }

    private function account(FinancialAccount $account): array
    {
        $installments = $account->installments->map(fn (FinancialInstallment $installment) => [
            'id' => $installment->id,
            'label' => $installment->label,
            'due_date' => $installment->due_date?->toDateString(),
            'amount' => (float) $installment->amount,
            'remaining' => (float) Money::amount(max(0, Money::cents($installment->amount) - Money::cents($installment->allocations_sum_amount ?? 0))),
            'status' => $installment->status,
        ]);
        $owner = $account->accountable;
        [$title, $context] = $account->domain === 'school'
            ? ['Scolarité '.($owner?->academicYear?->name ?? ''), collect([$owner?->level?->name, $owner?->group?->name])->filter()->join(' · ')]
            : [$owner?->form?->course?->title ?? $owner?->trainingPlanGroup?->plan?->level?->course?->title ?? 'Formation', collect([$owner?->form?->title, $owner?->trainingPlanGroup?->name])->filter()->join(' · ')];

        return [
            'id' => $account->id,
            'domain' => $account->domain,
            'title' => trim($title),
            'context' => $context,
            'status' => $account->status,
            'closed' => $account->closed_at !== null,
            'expected' => (float) $account->expected_total,
            'credits' => -(float) $account->adjustment_total,
            'paid' => (float) $account->paid_total,
            'balance' => (float) $account->balance,
            'overdue' => (float) $installments->where('status', 'overdue')->sum('remaining'),
            'next_due' => $installments->first(fn ($i) => $i['remaining'] > 0),
            'installments' => $installments->values()->all(),
            'payments' => $account->transactions->map(fn (FinancialTransaction $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'date' => $payment->transaction_date?->toDateString(),
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method?->label(),
                'cancelled' => $payment->reversal !== null,
            ])->values()->all(),
        ];
    }
}
