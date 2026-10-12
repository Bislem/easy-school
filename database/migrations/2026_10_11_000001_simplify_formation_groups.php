<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training plan groups are the only formation groups. A registration session
 * keeps its dates and overall capacity; students are placed in plan groups.
 * School groups have their own tables, so the school-era links on formation
 * groups are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_forms', function (Blueprint $table): void {
            $table->dropColumn(['groups_count', 'students_per_group']);
            $table->unsignedBigInteger('teacher_id')->nullable()->change();
        });
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropColumn('group_number'));
        Schema::table('timetable_sessions', function (Blueprint $table): void {
            $table->dropIndex('timetable_group_time_idx');
            $table->dropConstrainedForeignId('training_plan_group_id');
        });
        Schema::table('students', fn (Blueprint $table) => $table->dropConstrainedForeignId('training_plan_group_id'));
        Schema::table('training_plan_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_period_id');
            $table->dropConstrainedForeignId('school_level_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_plan_groups', function (Blueprint $table): void {
            $table->foreignId('school_level_id')->nullable()->after('training_plan_id')->constrained()->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->after('school_level_id')->constrained()->nullOnDelete();
        });
        Schema::table('students', fn (Blueprint $table) => $table->foreignId('training_plan_group_id')->nullable()->constrained()->nullOnDelete());
        Schema::table('timetable_sessions', fn (Blueprint $table) => $table->foreignId('training_plan_group_id')->nullable()->constrained()->restrictOnDelete());
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->unsignedInteger('group_number')->nullable());
        Schema::table('enrollment_forms', function (Blueprint $table): void {
            $table->unsignedInteger('groups_count')->default(1);
            $table->unsignedInteger('students_per_group')->nullable();
        });
    }
};
