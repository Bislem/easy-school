<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call(SchoolUsersSeeder::class);
        $this->call(DemoTeacherAccountConsolidationSeeder::class);
        $this->call(SchoolDemoSeeder::class);
        $this->call(StaffProfilesSeeder::class);
        $this->call(DemoEmployeeAttendanceSeeder::class);
    }
}
