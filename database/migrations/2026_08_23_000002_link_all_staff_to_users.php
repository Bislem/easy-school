<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('staff')->whereNull('user_id')->orderBy('id')->each(function ($staff) {
            $userId = filled($staff->email) ? DB::table('users')->where('email', $staff->email)->value('id') : null;
            if (! $userId) {
                $isTeacher = (bool) DB::table('employee_types')->where('id', $staff->employee_type_id)->value('is_teacher');
                $userId = DB::table('users')->insertGetId([
                    'name' => trim($staff->first_name.' '.$staff->last_name),
                    'email' => $staff->email ?: "staff-{$staff->id}@internal.invalid",
                    'phone' => $staff->phone,
                    'birth_date' => $staff->birth_date,
                    'role' => $isTeacher ? 'teacher' : 'employee',
                    'job_title' => DB::table('employee_types')->where('id', $staff->employee_type_id)->value('name'),
                    'can_login' => false,
                    'is_active' => $staff->employment_status === 'active',
                    'password' => Hash::make(str()->random(40)),
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('staff')->where('id', $staff->id)->update(['user_id' => $userId, 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
