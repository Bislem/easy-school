<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->whereNotIn('role', [UserRole::ADMIN->value, UserRole::TEACHER->value])
            ->update([
                'role' => UserRole::TEACHER->value,
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The previous unsupported role cannot be restored safely.
    }
};
