<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('teacher_manage_planning_groups')->default(false);
            $table->boolean('teacher_add_planning_sessions')->default(false);
            $table->boolean('teacher_record_student_attendance')->default(false);
        });
    }
    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn(['teacher_manage_planning_groups', 'teacher_add_planning_sessions', 'teacher_record_student_attendance']));
    }
};
