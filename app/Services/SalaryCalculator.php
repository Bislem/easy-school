<?php

namespace App\Services;

use App\Enums\SalaryType;
use App\Models\SalaryConfiguration;
use App\Models\Staff;
use App\Models\TeacherAttendance;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SalaryCalculator
{
    public function calculate(Staff $staff, SalaryConfiguration $configuration, CarbonInterface $start, CarbonInterface $end, ?float $manualUnits = null, ?float $manualAmount = null): array
    {
        $rate = (float) $configuration->base_rate; $units = 1.0; $validatedTeaching = collect();
        $isTeachingSalary = in_array($configuration->salary_type, [SalaryType::HOURLY, SalaryType::PER_SESSION], true)
            || ($configuration->salary_type === SalaryType::DAILY && (bool) $staff->employeeType?->is_teacher);
        if ($isTeachingSalary) {
            if (! $staff->user_id) throw ValidationException::withMessages(['staff_id'=>'Cet employé ne possède pas de compte enseignant lié.']);
            $validatedTeaching = TeacherAttendance::with('session')
                ->where('actual_teacher_id', $staff->user_id)
                ->whereNotNull('validated_at')
                ->whereIn('status', ['present', 'late', 'replaced'])
                ->whereDoesntHave('salaryStatements')
                ->whereHas('session', fn ($query) => $query->where('status', 'completed')
                    ->whereBetween('starts_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]))
                ->get()->sortBy('session.starts_at')->values();
            if ($validatedTeaching->isEmpty()) {
                throw ValidationException::withMessages(['period'=>'Aucune heure enseignante validée et non comptabilisée pour ce mois.']);
            }
        }
        $gross = match($configuration->salary_type) {
            SalaryType::MONTHLY => $rate,
            SalaryType::HOURLY => $rate * ($units = round($validatedTeaching->sum('worked_minutes') / 60, 2)),
            SalaryType::PER_SESSION => $rate * ($units = (float) $validatedTeaching->count()),
            SalaryType::DAILY => $rate * ($units = $isTeachingSalary
                ? (float) $validatedTeaching->pluck('session.starts_at')->map(fn ($date) => $date->toDateString())->unique()->count()
                : $this->requiredUnits($manualUnits,'worked_units')),
            SalaryType::CUSTOM => $manualAmount ?? throw ValidationException::withMessages(['manual_amount'=>'Le montant manuel est obligatoire.']),
        };
        return ['units'=>$units,'gross'=>round($gross,2),'details'=>[
            'teacher_attendance_ids'=>$validatedTeaching->pluck('id')->all(),
            'session_ids'=>$validatedTeaching->pluck('training_session_id')->all(),
            'session_count'=>$validatedTeaching->count(),
            'validated_worked_hours'=>$validatedTeaching->isNotEmpty()?round($validatedTeaching->sum('worked_minutes')/60,2):null,
            'period_start'=>$start->toDateString(),'period_end'=>$end->toDateString()
        ]];
    }

    private function requiredUnits(?float $units, string $field): float
    {
        if ($units === null || $units < 0) throw ValidationException::withMessages([$field=>'Le nombre d’unités travaillées est obligatoire.']);
        return $units;
    }
}
