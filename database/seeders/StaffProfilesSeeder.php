<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EmployeeType;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffProfilesSeeder extends Seeder
{
    /**
     * Ensure every teacher and employee login has the staff profile required
     * by the portal, attendance, badge and salary modules.
     */
    public function run(): void
    {
        $teacherType = EmployeeType::updateOrCreate(
            ['slug' => 'teacher'],
            ['name' => 'Enseignant', 'is_teacher' => true, 'is_active' => true, 'sort_order' => 0],
        );
        $employeeType = EmployeeType::updateOrCreate(
            ['slug' => 'other'],
            ['name' => 'Autre', 'is_teacher' => false, 'is_active' => true, 'sort_order' => 99],
        );

        User::query()
            ->whereIn('role', [UserRole::TEACHER->value, UserRole::EMPLOYEE->value])
            ->orderBy('id')
            ->each(function (User $user) use ($teacherType, $employeeType): void {
                $parts = preg_split('/\s+/', trim($user->name), 2);
                $type = $user->role === UserRole::TEACHER ? $teacherType : $employeeType;

                Staff::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'employee_type_id' => $type->id,
                        'first_name' => $parts[0] ?: $user->name,
                        'last_name' => $parts[1] ?? '',
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'birth_date' => $user->birth_date,
                        'employment_status' => $user->is_active ? 'active' : 'inactive',
                        'employee_code' => 'EMP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                        'notes' => $user->job_title,
                    ],
                );
            });
    }
}
