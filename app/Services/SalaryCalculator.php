<?php

namespace App\Services;

use App\Enums\SalaryType;
use App\Models\SalaryConfiguration;
use App\Models\Staff;
use App\Models\TrainingSession;
use App\Models\TeacherAttendance;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SalaryCalculator
{
    public function calculate(Staff $staff, SalaryConfiguration $configuration, CarbonInterface $start, CarbonInterface $end, ?float $manualUnits = null, ?float $manualAmount = null): array
    {
        abort_unless($configuration->staff_id === $staff->id, 422);
        $rate = (float) $configuration->base_rate; $sessions = collect(); $units = 1.0; $validatedTeaching = collect();
        if (in_array($configuration->salary_type, [SalaryType::HOURLY, SalaryType::PER_SESSION], true)) {
            if (! $staff->user_id) throw ValidationException::withMessages(['staff_id'=>'Cet employé ne possède pas de compte enseignant lié.']);
            $sessions = TrainingSession::where(fn($query)=>$query->where('teacher_id',$staff->user_id)->orWhereHas('teacherAttendance',fn($attendance)=>$attendance->where('actual_teacher_id',$staff->user_id)))
                ->where('status','completed')->whereBetween('starts_at',[$start->copy()->startOfDay(),$end->copy()->endOfDay()])->orderBy('starts_at')->get();
            $validatedTeaching=TeacherAttendance::whereIn('training_session_id',$sessions->pluck('id'))->where('actual_teacher_id',$staff->user_id)->whereNotNull('validated_at')->whereIn('status',['present','late','replaced'])->get();
        }
        $gross = match($configuration->salary_type) {
            SalaryType::MONTHLY => $rate,
            SalaryType::HOURLY => $rate * ($units = round(($validatedTeaching->isNotEmpty()?$validatedTeaching->sum('worked_minutes'):$sessions->sum(fn($session)=>$session->starts_at->diffInMinutes($session->ends_at)))/60,2)),
            SalaryType::PER_SESSION => $rate * ($units = (float)($validatedTeaching->isNotEmpty()?$validatedTeaching->count():$sessions->count())),
            SalaryType::DAILY => $rate * ($units = $this->requiredUnits($manualUnits,'worked_units')),
            SalaryType::CUSTOM => $manualAmount ?? throw ValidationException::withMessages(['manual_amount'=>'Le montant manuel est obligatoire.']),
        };
        return ['units'=>$units,'gross'=>round($gross,2),'details'=>['session_ids'=>$sessions->pluck('id')->all(),'session_count'=>$sessions->count(),'validated_worked_hours'=>$configuration->salary_type===SalaryType::HOURLY?$units:null,'completed_hours'=>round($sessions->sum(fn($session)=>$session->starts_at->diffInMinutes($session->ends_at))/60,2),'period_start'=>$start->toDateString(),'period_end'=>$end->toDateString()]];
    }

    private function requiredUnits(?float $units, string $field): float
    {
        if ($units === null || $units < 0) throw ValidationException::withMessages([$field=>'Le nombre d’unités travaillées est obligatoire.']);
        return $units;
    }
}
