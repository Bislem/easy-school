<?php

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTeacherAccountConsolidationSeeder extends Seeder
{
    /** Consolidate the historical duplicate Yacine demo login into the canonical account. */
    public function run(): void
    {
        $canonical = User::where('email', 'teacher@easyschool.test')->first();
        $duplicate = User::where('email', 'yacine.benali@easyschool.test')->first();
        if (! $canonical || ! $duplicate || $canonical->is($duplicate)) {
            return;
        }

        DB::transaction(function () use ($canonical, $duplicate): void {
            $sourceStaff = Staff::where('user_id', $duplicate->id)->first();
            $targetStaff = Staff::where('user_id', $canonical->id)->first();

            if ($sourceStaff && $targetStaff && ! $sourceStaff->is($targetStaff)) {
                foreach (['employee_attendances', 'expenses', 'salary_configurations', 'salary_payments', 'salary_statements'] as $table) {
                    DB::table($table)->where('staff_id', $targetStaff->id)->update(['staff_id' => $sourceStaff->id]);
                }
                DB::table('badges')->where('badgeable_type', Staff::class)->where('badgeable_id', $targetStaff->id)->update(['badgeable_id' => $sourceStaff->id]);
                $targetStaff->delete();
            }

            foreach ([
                ['attendance_histories', 'user_id'], ['audit_logs', 'user_id'],
                ['badges', 'status_changed_by'], ['badges', 'issued_by'], ['certificates', 'issued_by'],
                ['drivers', 'approved_by'], ['drivers', 'user_id'], ['employee_attendances', 'locked_by'],
                ['employee_attendances', 'recorded_by'], ['enrollment_financial_adjustments', 'created_by'],
                ['enrollment_forms', 'teacher_id'], ['enrollment_histories', 'user_id'],
                ['expenses', 'created_by'], ['expenses', 'employee_id'], ['fuel_tank_records', 'recorded_by'],
                ['payments', 'user_id'], ['portal_notifications', 'recipient_id'], ['reservations', 'user_id'],
                ['salary_payments', 'created_by'], ['salary_statements', 'generated_by'],
                ['session_attendances', 'recorded_by'], ['student_histories', 'user_id'],
                ['student_payments', 'recorded_by'], ['students', 'user_id'], ['parents', 'user_id'],
                ['tickets', 'user_id'], ['training_plans', 'teacher_id'], ['training_sessions', 'attendance_locked_by'],
            ] as [$table, $column]) {
                DB::table($table)->where($column, $duplicate->id)->update([$column => $canonical->id]);
            }
            foreach (['actual_teacher_id', 'scheduled_teacher_id', 'recorded_by'] as $column) {
                DB::table('teacher_attendances')->where($column, $duplicate->id)->update([$column => $canonical->id]);
            }
            DB::table('training_sessions')->where('teacher_id', $duplicate->id)->update(['teacher_id' => $canonical->id]);

            if ($sourceStaff) {
                $sourceStaff->update(['user_id' => $canonical->id, 'email' => $canonical->email]);
            }
            $duplicate->delete();
        });
    }
}
