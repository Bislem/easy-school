<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\FinancialAccount;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Database\Eloquent\Builder;

final class PaymentsOverviewQuery
{
    public function accounts(array $filters): Builder
    {
        return $this->apply(FinancialAccount::query(), $filters);
    }

    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when(($filters['source'] ?? 'all') !== 'all', fn (Builder $query) => $query->where('domain', $filters['source']))
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['student_id'] ?? null, fn (Builder $query, $id) => $query->where('student_id', $id))
            ->when($filters['level_id'] ?? null, fn (Builder $query, $id) => $query->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn (Builder $enrollment) => $enrollment->where('school_level_id', $id)))
            ->when($filters['group_id'] ?? null, function (Builder $query, string $group): void {
                [$domain, $id] = explode(':', $group, 2);
                $domain === 'school'
                    ? $query->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn (Builder $enrollment) => $enrollment->where('school_group_id', $id))
                    : $query->whereHasMorph('accountable', [CourseEnrollment::class], fn (Builder $enrollment) => $enrollment->where('training_plan_group_id', $id));
            })
            ->when($filters['formation_id'] ?? null, fn (Builder $query, $id) => $query->whereHasMorph('accountable', [CourseEnrollment::class], fn (Builder $enrollment) => $enrollment->whereHas('form', fn (Builder $form) => $form->where('course_id', $id))))
            ->when($filters['session_id'] ?? null, fn (Builder $query, $id) => $query->whereHasMorph('accountable', [CourseEnrollment::class], fn (Builder $enrollment) => $enrollment->where('enrollment_form_id', $id)))
            ->when($filters['site_id'] ?? null, function (Builder $query, $id): void {
                $query->where(function (Builder $accounts) use ($id): void {
                    $accounts->whereHasMorph('accountable', [StudentAcademicEnrollment::class], fn (Builder $enrollment) => $enrollment->whereHas('group.classroom', fn (Builder $classroom) => $classroom->where('school_site_id', $id)))
                        ->orWhereHasMorph('accountable', [CourseEnrollment::class], fn (Builder $enrollment) => $enrollment->where(fn (Builder $enrollment) => $enrollment->whereHas('trainingPlanGroup.classroom', fn (Builder $classroom) => $classroom->where('school_site_id', $id))->orWhereHas('form.classroom', fn (Builder $classroom) => $classroom->where('school_site_id', $id))));
                });
            })
            ->when($filters['payment_method'] ?? null, fn (Builder $query, $method) => $query->whereHas('transactions', fn (Builder $transaction) => $transaction->where('payment_method', $method)))
            ->when(($filters['date_from'] ?? null) || ($filters['date_to'] ?? null), fn (Builder $query) => $query->where(function (Builder $accounts) use ($filters): void {
                $accounts->whereHas('installments', fn (Builder $installment) => $this->dates($installment, 'due_date', $filters))
                    ->orWhereHas('transactions', fn (Builder $transaction) => $this->dates($transaction, 'transaction_date', $filters));
            }));
    }

    public function dates(Builder $query, string $column, array $filters): Builder
    {
        return $query
            ->when($filters['date_from'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '<=', $date));
    }
}
