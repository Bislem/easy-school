<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = DB::table('employee_types')->pluck('id', 'slug');
        DB::table('users')->whereIn('role', ['teacher', 'employee'])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('staff')->whereColumn('staff.user_id', 'users.id'))
            ->orderBy('id')->each(function ($user) use ($types) {
                $parts = preg_split('/\s+/', trim($user->name), 2);
                DB::table('staff')->insert([
                    'user_id' => $user->id,
                    'employee_type_id' => $types[$user->role === 'teacher' ? 'teacher' : 'other'],
                    'first_name' => $parts[0] ?: $user->name,
                    'last_name' => $parts[1] ?? '',
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'birth_date' => $user->birth_date,
                    'employment_status' => $user->is_active ? 'active' : 'inactive',
                    'employee_code' => 'EMP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void {}
};
