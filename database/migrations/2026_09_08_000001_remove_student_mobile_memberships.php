<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mobile_memberships')->where('role', 'student')->delete();
        if (Schema::hasColumn('mobile_memberships', 'student_id')) {
            Schema::table('mobile_memberships', function (Blueprint $table) {
                $table->dropConstrainedForeignId('student_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mobile_memberships', 'student_id')) {
            Schema::table('mobile_memberships', function (Blueprint $table) {
                $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            });
        }
    }
};
