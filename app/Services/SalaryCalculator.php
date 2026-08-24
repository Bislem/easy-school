<?php

namespace App\Services;

use App\Enums\SalaryType;
use App\Models\EmployeeAttendance;
use App\Models\SalaryConfiguration;
use App\Models\Staff;
use App\Models\TeacherAttendance;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SalaryCalculator
{
    public function attendanceSnapshot(Staff $staff, CarbonInterface $start, CarbonInterface $end, bool $onlyUnaccounted = true): array
    {
        $isTeacher = (bool) $staff->loadMissing('employeeType')->employeeType?->is_teacher;
        $teacherAttendances = collect();
        $employeeAttendances = collect();

        if ($isTeacher) {
            if (! $staff->user_id) {
                throw ValidationException::withMessages(['staff_id' => 'Cet employé ne possède pas de compte enseignant lié.']);
            }
            $teacherAttendances = TeacherAttendance::with('session')
                ->where('actual_teacher_id', $staff->user_id)
                ->whereNotNull('validated_at')
                ->whereIn('status', ['present', 'late', 'replaced'])
                ->when($onlyUnaccounted, fn ($query) => $query->whereDoesntHave('salaryStatements'))
                ->whereHas('session', fn ($query) => $query->whereBetween('starts_at', [
                    $start->copy()->startOfDay(), $end->copy()->endOfDay(),
                ]))->get()->sortBy('session.starts_at')->values();
        } else {
            $employeeAttendances = EmployeeAttendance::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->when($onlyUnaccounted, fn ($query) => $query->whereDoesntHave('salaryStatements'))
                ->orderBy('attendance_date')->get();
        }

        $payableEmployeeAttendances = $employeeAttendances->whereIn('status', ['present', 'late']);
        $workedMinutes = $isTeacher
            ? $teacherAttendances->sum('worked_minutes')
            : $payableEmployeeAttendances->sum('worked_minutes');
        $workedDays = $isTeacher
            ? $teacherAttendances->pluck('session.starts_at')->map(fn ($date) => $date->toDateString())->unique()->count()
            : $payableEmployeeAttendances->pluck('attendance_date')->map(fn ($date) => $date->toDateString())->unique()->count();

        return [
            'is_teacher' => $isTeacher,
            'teacher_attendance_ids' => $teacherAttendances->pluck('id')->all(),
            'employee_attendance_ids' => $employeeAttendances->pluck('id')->all(),
            'session_ids' => $teacherAttendances->pluck('training_session_id')->all(),
            'session_count' => $teacherAttendances->count(),
            'worked_hours' => round($workedMinutes / 60, 2),
            'worked_days' => (float) $workedDays,
        ];
    }

    public function calculate(Staff $staff, SalaryConfiguration $configuration, CarbonInterface $start, CarbonInterface $end, ?float $manualUnits = null, ?float $manualAmount = null): array
    {
        $rate = (float) $configuration->base_rate;
        $snapshot = $this->attendanceSnapshot($staff, $start, $end);

        if (in_array($configuration->salary_type, [SalaryType::HOURLY, SalaryType::PER_SESSION, SalaryType::DAILY], true)
            && $snapshot['teacher_attendance_ids'] === [] && $snapshot['employee_attendance_ids'] === []) {
            throw ValidationException::withMessages(['period' => 'Aucun pointage non comptabilisé pour ce mois.']);
        }

        $units = match ($configuration->salary_type) {
            SalaryType::MONTHLY, SalaryType::CUSTOM => 1.0,
            SalaryType::HOURLY => $snapshot['worked_hours'],
            SalaryType::PER_SESSION => $snapshot['is_teacher'] ? (float) $snapshot['session_count'] : $snapshot['worked_days'],
            SalaryType::DAILY => $manualUnits ?? $snapshot['worked_days'],
        };
        $gross = match ($configuration->salary_type) {
            SalaryType::MONTHLY => $rate,
            SalaryType::CUSTOM => $manualAmount ?? throw ValidationException::withMessages(['manual_amount' => 'Le montant manuel est obligatoire.']),
            default => $rate * $units,
        };

        return ['units' => $units, 'gross' => round($gross, 2), 'details' => [
            'teacher_attendance_ids' => $snapshot['teacher_attendance_ids'],
            'employee_attendance_ids' => $snapshot['employee_attendance_ids'],
            'session_ids' => $snapshot['session_ids'],
            'session_count' => $snapshot['session_count'],
            'original_attendance_units' => $snapshot['worked_hours'],
            'attendance_worked_hours' => $snapshot['worked_hours'],
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
        ]];
    }
}
