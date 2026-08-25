<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EmployeeAttendance;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoEmployeeAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $recorder = User::where('role', UserRole::ADMIN->value)->value('id');
        if (! $recorder) return;

        $staffMembers = Staff::whereHas('user', fn ($query) => $query->whereIn('email', [
            'nadia.merabet@easyschool.test',
            'karim.haddad@easyschool.test',
        ]))->get();

        $dates = collect(range(1, now()->daysInMonth))
            ->map(fn (int $day) => now()->copy()->startOfMonth()->day($day))
            ->filter(fn (Carbon $date) => $date->isWeekday() && $date->lte(today()))
            ->take(18);

        foreach ($staffMembers as $staff) {
            foreach ($dates as $date) {
                EmployeeAttendance::firstOrCreate(
                    ['staff_id' => $staff->id, 'attendance_date' => $date->toDateString()],
                    [
                        'status' => 'present',
                        'check_in' => '08:30:00',
                        'check_out' => '16:30:00',
                        'worked_minutes' => 480,
                        'recorded_by' => $recorder,
                    ],
                );
            }
        }
    }
}
