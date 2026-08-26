<?php

namespace App\Services;

use App\Models\AnnualLeave;
use App\Models\Staff;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class AnnualLeaveService
{
    public const DAYS_PER_MONTH = 2.5;

    public function summary(Staff $staff, ?CarbonInterface $asOf = null, ?AnnualLeave $except = null): array
    {
        $asOf ??= now();
        $baselineDate = $staff->leave_balance_as_of ?: $staff->hire_date;
        $opening = $staff->leave_balance_as_of ? (float) ($staff->leave_opening_balance ?? 0) : 0.0;
        $months = $baselineDate && $baselineDate->lte($asOf)
            ? (int) floor($baselineDate->diffInMonths($asOf)) : 0;
        $earnedSinceBaseline = $months * self::DAYS_PER_MONTH;
        $accrued = $opening + $earnedSinceBaseline;
        $query = $staff->annualLeaves()
            ->when($staff->leave_balance_as_of, fn ($q) => $q->whereDate('starts_at', '>=', $staff->leave_balance_as_of))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id));
        $used = (float) (clone $query)->whereIn('status', ['approved', 'taken'])->sum('days');
        $pending = (float) (clone $query)->where('status', 'pending')->sum('days');

        return ['completed_months' => $months, 'rate' => self::DAYS_PER_MONTH, 'opening_balance' => $opening, 'balance_as_of' => $staff->leave_balance_as_of?->format('Y-m-d'), 'earned_since_baseline' => $earnedSinceBaseline, 'accrued' => $accrued, 'used' => $used, 'pending' => $pending, 'available' => max(0, $accrued - $used - $pending)];
    }

    public function assertCanReserve(Staff $staff, CarbonInterface $start, CarbonInterface $end, float $days, ?AnnualLeave $except = null): void
    {
        if (! $staff->hire_date) {
            throw ValidationException::withMessages(['starts_at' => "La date d'embauche doit être renseignée."]);
        }
        if ($start->lt($staff->hire_date)) {
            throw ValidationException::withMessages(['starts_at' => "Le congé ne peut pas précéder la date d'embauche."]);
        }
        $overlap = $staff->annualLeaves()->whereIn('status', ['pending', 'approved', 'taken'])
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereDate('starts_at', '<=', $end)->whereDate('ends_at', '>=', $start)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Cette période chevauche un congé existant.']);
        }
        $available = $this->summary($staff, $end, $except)['available'];
        if ($days > $available) {
            throw ValidationException::withMessages(['days' => "Solde insuffisant : {$available} jour(s) disponible(s)."]);
        }
    }
}
