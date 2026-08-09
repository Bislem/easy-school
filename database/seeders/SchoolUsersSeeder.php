<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SchoolUsersSeeder extends Seeder
{
    /**
     * Seed the administrator and teacher accounts allowed into the portal.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $users = [
            [
                'name' => 'School Administrator',
                'email' => 'admin@easyschool.test',
                'phone' => '0550 00 00 01',
                'role' => UserRole::ADMIN,
                'birth_date' => '1985-01-15',
            ],
            [
                'name' => 'Yacine Benali',
                'email' => 'teacher@easyschool.test',
                'phone' => '0550 00 00 02',
                'role' => UserRole::TEACHER,
                'birth_date' => '1990-04-22',
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
