<?php

namespace App\Services;

use App\Enums\SalaryCalculationType;
use App\Enums\SalaryItemCategory;
use App\Enums\SalaryType;
use App\Models\AcademicYearCalendarEvent;
use App\Models\EmployeeAttendance;
use App\Models\SalaryConfiguration;
use App\Models\SalaryItem;
use App\Models\Staff;
use App\Models\TeacherAttendance;
use App\Models\TimetableSetting;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
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
        $attendanceDates = $isTeacher
            ? $teacherAttendances->pluck('session.starts_at')->map(fn ($date) => $date->toDateString())->unique()
            : $payableEmployeeAttendances->pluck('attendance_date')->map(fn ($date) => $date->toDateString())->unique();
        $paidCalendar = $isTeacher
            ? $this->paidTeacherCalendarDays($start, $end, $attendanceDates->all())
            : ['days' => 0, 'dates' => [], 'event_ids' => []];
        $workedDays = $isTeacher
            ? $attendanceDates->count() + $paidCalendar['days']
            : $payableEmployeeAttendances->pluck('attendance_date')->map(fn ($date) => $date->toDateString())->unique()->count();

        return [
            'is_teacher' => $isTeacher,
            'teacher_attendance_ids' => $teacherAttendances->pluck('id')->all(),
            'employee_attendance_ids' => $employeeAttendances->pluck('id')->all(),
            'session_ids' => $teacherAttendances->pluck('training_session_id')->all(),
            'session_count' => $teacherAttendances->count(),
            'worked_hours' => round($workedMinutes / 60, 2),
            'worked_days' => (float) $workedDays,
            'attendance_days' => (float) $attendanceDates->count(),
            'paid_calendar_days' => (float) $paidCalendar['days'],
            'paid_calendar_dates' => $paidCalendar['dates'],
            'paid_calendar_event_ids' => $paidCalendar['event_ids'],
        ];
    }

    public function calculate(Staff $staff, SalaryConfiguration $configuration, CarbonInterface $start, CarbonInterface $end, ?float $manualUnits = null, ?float $manualAmount = null): array
    {
        $rate = (float) $configuration->base_rate;
        $snapshot = $this->attendanceSnapshot($staff, $start, $end);

        if (in_array($configuration->salary_type, [SalaryType::HOURLY, SalaryType::PER_SESSION, SalaryType::DAILY], true)
            && $snapshot['teacher_attendance_ids'] === [] && $snapshot['employee_attendance_ids'] === [] && $snapshot['paid_calendar_days'] === 0.0) {
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
            'attendance_worked_days' => $snapshot['attendance_days'],
            'paid_calendar_days' => $snapshot['paid_calendar_days'],
            'paid_calendar_dates' => $snapshot['paid_calendar_dates'],
            'paid_calendar_event_ids' => $snapshot['paid_calendar_event_ids'],
            'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
        ]];
    }

    /** Calculate every selected reusable item and return immutable line payloads. */
    public function calculateItems(Staff $staff, iterable $selectedItems, CarbonInterface $start, CarbonInterface $end): array
    {
        $snapshot = $this->attendanceSnapshot($staff, $start, $end);
        $lines = collect($selectedItems)->values()->map(function (array $selected, int $index) use ($snapshot) {
            /** @var SalaryItem $item */
            $item = $selected['item'];
            $rate = (float) ($selected['amount'] ?? $item->default_amount ?? 0);
            $quantity = match ($item->calculation_type) {
                SalaryCalculationType::HOURLY => (float) $snapshot['worked_hours'],
                SalaryCalculationType::DAILY => (float) $snapshot['worked_days'],
                SalaryCalculationType::PER_SESSION => $snapshot['is_teacher'] ? (float) $snapshot['session_count'] : (float) $snapshot['worked_days'],
                default => 1.0,
            };
            $unsignedAmount = round($rate * $quantity, 2);
            $amount = $item->category === SalaryItemCategory::DEDUCTION ? -$unsignedAmount : $unsignedAmount;

            return [
                'salary_item_id' => $item->id,
                'item_name' => $item->name,
                'item_code' => $item->code,
                'category' => $item->category->value,
                'calculation_type' => $item->calculation_type->value,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount,
                'source' => $selected['source'] ?? 'DIRECT',
                'display_order' => $selected['display_order'] ?? $index,
                'subject_to_cnas' => $item->subject_to_cnas,
                'subject_to_irg' => $item->subject_to_irg,
                'salary_item_nature' => $item->salary_item_nature,
                'irg_treatment' => $item->irg_treatment,
                'snapshot_data' => ['description' => $item->description, 'default_amount' => (float) $item->default_amount, 'subject_to_cnas' => $item->subject_to_cnas, 'subject_to_irg' => $item->subject_to_irg, 'salary_item_nature' => $item->salary_item_nature, 'irg_treatment' => $item->irg_treatment],
            ];
        });

        if ($lines->contains(fn (array $line) => in_array($line['calculation_type'], [SalaryCalculationType::HOURLY->value, SalaryCalculationType::DAILY->value, SalaryCalculationType::PER_SESSION->value], true))
            && $snapshot['teacher_attendance_ids'] === [] && $snapshot['employee_attendance_ids'] === [] && $snapshot['paid_calendar_days'] === 0.0) {
            throw ValidationException::withMessages(['period' => 'Aucun pointage non comptabilisé pour ce mois.']);
        }

        return [
            'lines' => $lines->all(),
            'gross' => round((float) $lines->where('amount', '>', 0)->sum('amount'), 2),
            'item_deductions' => round(abs((float) $lines->where('amount', '<', 0)->sum('amount')), 2),
            'details' => [
                'teacher_attendance_ids' => $snapshot['teacher_attendance_ids'],
                'employee_attendance_ids' => $snapshot['employee_attendance_ids'],
                'session_ids' => $snapshot['session_ids'],
                'session_count' => $snapshot['session_count'],
                'original_attendance_units' => $snapshot['worked_hours'],
                'attendance_worked_hours' => $snapshot['worked_hours'],
                'attendance_worked_days' => $snapshot['attendance_days'],
                'paid_calendar_days' => $snapshot['paid_calendar_days'],
                'paid_calendar_dates' => $snapshot['paid_calendar_dates'],
                'paid_calendar_event_ids' => $snapshot['paid_calendar_event_ids'],
                'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
            ],
        ];
    }

    private function paidTeacherCalendarDays(CarbonInterface $start, CarbonInterface $end, array $alreadyWorkedDates): array
    {
        $events = AcademicYearCalendarEvent::query()
            ->where('is_paid_for_teachers', true)
            ->whereIn('applies_to', ['both', 'teachers'])
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->get();
        $workingDays = TimetableSetting::first()?->working_days ?? TimetableSetting::defaults()['working_days'];
        $dates = $events->flatMap(function (AcademicYearCalendarEvent $event) use ($start, $end, $workingDays) {
            $from = $event->starts_on->greaterThan($start) ? $event->starts_on : $start;
            $to = $event->ends_on->lessThan($end) ? $event->ends_on : $end;

            return collect(CarbonPeriod::create($from, $to))
                ->filter(fn ($date) => in_array($date->dayOfWeekIso, $workingDays, true))
                ->map->toDateString();
        })->unique()->diff($alreadyWorkedDates)->sort()->values();

        return ['days' => $dates->count(), 'dates' => $dates->all(), 'event_ids' => $events->pluck('id')->all()];
    }
}
