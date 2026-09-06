<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_plan_groups', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->foreignId('academic_period_id')->nullable()->after('school_level_id')->constrained()->nullOnDelete();
            $table->foreignId('principal_teacher_id')->nullable()->after('classroom_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('capacity')->index();
        });
        DB::table('training_plan_groups')->orderBy('id')->eachById(function ($group) {
            DB::table('training_plan_groups')->where('id', $group->id)->update(['code' => 'GRP-'.$group->id]);
        });
        Schema::table('training_plan_groups', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
            $table->unsignedBigInteger('training_plan_id')->nullable()->change();
        });

        DB::table('students')->whereNull('training_plan_group_id')->orderBy('id')->eachById(function ($student) {
            $groupId = DB::table('course_enrollments')->where('student_id', $student->id)->where('status', 'registered')->whereNotNull('training_plan_group_id')->latest('id')->value('training_plan_group_id');
            if ($groupId) {
                DB::table('students')->where('id', $student->id)->update(['training_plan_group_id' => $groupId]);
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('weekly_hours', 5, 2)->nullable()->after('duration_hours');
            $table->string('color', 20)->default('#2563eb')->after('category');
            $table->boolean('is_specialized')->default(false)->after('required_room_types')->index();
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['weekly_hours', 'color', 'is_specialized']));
        Schema::table('training_plan_groups', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropConstrainedForeignId('principal_teacher_id');
            $table->dropConstrainedForeignId('academic_period_id');
            $table->dropColumn(['code', 'is_active']);
        });
    }
};
