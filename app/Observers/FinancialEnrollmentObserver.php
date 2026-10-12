<?php

namespace App\Observers;

use App\Enums\ApplicationStatus;
use App\Enums\StudentAcademicEnrollmentStatus;
use App\Models\CourseEnrollment;
use App\Models\FinancialAccount;
use App\Models\StudentAcademicEnrollment;
use App\Services\AuthorizationService;
use App\Services\FinancialAccountService;
use App\Services\FormationBillingService;
use App\Services\SchoolFeeGenerationService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/** Keeps billing accounts in step with the school and formation enrollment lifecycles. */
final class FinancialEnrollmentObserver
{
    public function saved(Model $enrollment): void
    {
        if (! $enrollment->tenant_id) {
            return;
        }
        app(TenantContext::class)->runAs((int) $enrollment->tenant_id, fn () => $enrollment instanceof StudentAcademicEnrollment
            ? $this->school($enrollment)
            : $this->formation($enrollment));
    }

    private function school(StudentAcademicEnrollment $enrollment): void
    {
        $account = $this->account($enrollment);
        match ($enrollment->status) {
            StudentAcademicEnrollmentStatus::ENROLLED => $this->activate($enrollment, $account, ['school_level_id', 'school_group_id'], fn (bool $update) => app(SchoolFeeGenerationService::class)->generateForEnrollment($enrollment, null, $update)),
            StudentAcademicEnrollmentStatus::WITHDRAWN => $account && app(FinancialAccountService::class)->close($account, 'Élève retiré', auth()->id()),
            StudentAcademicEnrollmentStatus::TRANSFERRED => $account && app(FinancialAccountService::class)->close($account, 'Élève transféré', auth()->id()),
            default => null,
        };
    }

    private function formation(CourseEnrollment $enrollment): void
    {
        if (! $enrollment->student_id) {
            return;
        }
        $account = $this->account($enrollment);
        match ($enrollment->status) {
            ApplicationStatus::REGISTERED => $this->activate($enrollment, $account, ['enrollment_form_id', 'training_plan_group_id'], fn (bool $update) => app(FormationBillingService::class)->generateForEnrollment($enrollment, null, $update)),
            ApplicationStatus::CANCELLED, ApplicationStatus::REJECTED => $account && app(FinancialAccountService::class)->close($account, 'Inscription annulée', auth()->id()),
            default => null,
        };
    }

    private function activate(Model $enrollment, ?FinancialAccount $account, array $pricingColumns, callable $generate): void
    {
        if ($account?->closed_at) {
            app(FinancialAccountService::class)->reopen($account, auth()->id());

            return;
        }
        if (! $account) {
            $generate(false);
        } elseif ($enrollment->wasChanged($pricingColumns)) {
            // Re-price only while nothing has been recorded on the account.
            $generate(true);
        }
    }

    private function account(Model $enrollment): ?FinancialAccount
    {
        return FinancialAccount::withoutGlobalScope(AuthorizationService::DATA_SCOPE)
            ->where('accountable_type', $enrollment->getMorphClass())->where('accountable_id', $enrollment->getKey())->first();
    }
}
