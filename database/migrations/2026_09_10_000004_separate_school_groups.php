<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('legacy_training_plan_group_id')->nullable()->unique()->constrained('training_plan_groups')->nullOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('principal_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('school_group_teacher', function (Blueprint $table) {
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('school_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['school_group_id', 'teacher_id']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('school_group_id')->nullable()->after('training_plan_group_id')->constrained()->nullOnDelete();
        });
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('school_group_id')->nullable()->after('training_plan_group_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('training_plan_group_id')->nullable()->change();
            $table->index(['school_group_id', 'day', 'start_time', 'end_time'], 'timetable_school_group_time_idx');
        });

        DB::table('training_plan_groups')
            ->whereNull('training_plan_id')
            ->whereNotNull('school_level_id')
            ->orderBy('id')
            ->eachById(function ($legacy): void {
                $schoolGroupId = DB::table('school_groups')->insertGetId([
                    'tenant_id' => $legacy->tenant_id,
                    'legacy_training_plan_group_id' => $legacy->id,
                    'school_level_id' => $legacy->school_level_id,
                    'academic_period_id' => $legacy->academic_period_id,
                    'classroom_id' => $legacy->classroom_id,
                    'principal_teacher_id' => $legacy->principal_teacher_id,
                    'name' => $legacy->name,
                    'code' => $legacy->code ?: 'GRP-'.$legacy->id,
                    'capacity' => $legacy->capacity,
                    'is_active' => $legacy->is_active,
                    'created_at' => $legacy->created_at,
                    'updated_at' => $legacy->updated_at,
                ]);

                DB::table('group_teacher')->where('training_plan_group_id', $legacy->id)->get()->each(function ($teacher) use ($schoolGroupId): void {
                    DB::table('school_group_teacher')->insertOrIgnore([
                        'tenant_id' => $teacher->tenant_id,
                        'school_group_id' => $schoolGroupId,
                        'teacher_id' => $teacher->teacher_id,
                        'created_at' => $teacher->created_at,
                        'updated_at' => $teacher->updated_at,
                    ]);
                });

                DB::table('students')->where('training_plan_group_id', $legacy->id)
                    ->update(['school_group_id' => $schoolGroupId, 'training_plan_group_id' => null]);
                DB::table('timetable_sessions')->where('training_plan_group_id', $legacy->id)
                    ->update(['school_group_id' => $schoolGroupId, 'training_plan_group_id' => null]);
            });
    }

    public function down(): void
    {
        DB::table('school_groups')->orderBy('id')->eachById(function ($group): void {
            if (! $group->legacy_training_plan_group_id) {
                return;
            }
            DB::table('students')->where('school_group_id', $group->id)->update(['training_plan_group_id' => $group->legacy_training_plan_group_id]);
            DB::table('timetable_sessions')->where('school_group_id', $group->id)->update(['training_plan_group_id' => $group->legacy_training_plan_group_id]);
        });

        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropIndex('timetable_school_group_time_idx');
            $table->dropConstrainedForeignId('school_group_id');
            $table->unsignedBigInteger('training_plan_group_id')->nullable(false)->change();
        });
        Schema::table('students', fn (Blueprint $table) => $table->dropConstrainedForeignId('school_group_id'));
        Schema::dropIfExists('school_group_teacher');
        Schema::dropIfExists('school_groups');
    }
};
