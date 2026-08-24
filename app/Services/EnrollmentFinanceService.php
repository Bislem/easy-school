<?php

namespace App\Services;

use App\Enums\EnrollmentPaymentStatus;
use App\Models\CourseEnrollment;

class EnrollmentFinanceService
{
    public function refresh(CourseEnrollment $enrollment): CourseEnrollment
    {
        $paid = (float) $enrollment->payments()->whereIn('status', ['completed', 'reversal'])->sum('amount');
        $base = (float) ($enrollment->formation_price
            ?? $enrollment->form()->with('course')->first()?->course?->price
            ?? $enrollment->trainingPlanGroup()->with('plan.level')->first()?->plan?->level?->price
            ?? 0);
        $final = max(0, $base - (float) $enrollment->discount_amount + (float) $enrollment->adjustment_total);
        $remaining = max(0, $final - $paid);
        $overdue = $remaining > 0 && $enrollment->installments()->where('due_date', '<', today())->whereIn('status', ['pending', 'partial', 'overdue'])->exists();
        $status = $remaining <= 0 ? EnrollmentPaymentStatus::PAID : ($overdue ? EnrollmentPaymentStatus::OVERDUE : ($paid > 0 ? EnrollmentPaymentStatus::PARTIALLY_PAID : EnrollmentPaymentStatus::UNPAID));
        $enrollment->updateQuietly(['formation_price'=>$base, 'final_price'=>$final, 'total_paid'=>$paid, 'remaining_balance'=>$remaining, 'payment_status'=>$status]);

        foreach ($enrollment->installments as $installment) {
            $allocated = (float) $installment->payments()->whereIn('status', ['completed', 'reversal'])->sum('amount');
            $installmentStatus = $allocated >= (float) $installment->amount ? 'paid' : ($allocated > 0 ? 'partial' : ($installment->due_date->isPast() ? 'overdue' : 'pending'));
            $installment->update(['status'=>$installmentStatus, 'paid_date'=>$installmentStatus === 'paid' ? ($installment->paid_date ?? today()) : null]);
        }
        return $enrollment->fresh();
    }
}
