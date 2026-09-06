<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('school_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('school_cycle_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('specialization')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'code', 'specialization']);
        });

        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('academic_year', 20);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'academic_year']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('type', 30)->default('classroom')->after('code')->index();
        });
        Schema::table('training_plan_groups', function (Blueprint $table) {
            $table->foreignId('school_level_id')->nullable()->after('training_plan_id')->constrained()->nullOnDelete();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('training_plan_group_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('course_school_level', function (Blueprint $table) {
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_level_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['course_id', 'school_level_id']);
        });
        Schema::create('course_teacher', function (Blueprint $table) {
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['course_id', 'teacher_id']);
        });
        Schema::create('group_teacher', function (Blueprint $table) {
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('training_plan_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['training_plan_group_id', 'teacher_id']);
        });

        Schema::create('timetable_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('training_plan_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('recurrence', 20)->default('weekly');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamps();
            $table->index(['academic_period_id', 'day', 'start_time', 'end_time'], 'timetable_period_time_idx');
            $table->index(['training_plan_group_id', 'day', 'start_time', 'end_time'], 'timetable_group_time_idx');
            $table->index(['teacher_id', 'day', 'start_time', 'end_time'], 'timetable_teacher_time_idx');
            $table->index(['classroom_id', 'day', 'start_time', 'end_time'], 'timetable_room_time_idx');
        });

        $levels = ['Primaire' => ['1AP', '2AP', '3AP', '4AP', '5AP'], 'CEM' => ['1AM', '2AM', '3AM', '4AM'], 'Lycée' => ['1AS', '2AS', '3AS']];
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($levels as $cycleName => $codes) {
                $cycleId = DB::table('school_cycles')->insertGetId(['tenant_id' => $tenantId, 'name' => $cycleName, 'code' => strtoupper($cycleName === 'Lycée' ? 'LYCEE' : $cycleName), 'sort_order' => array_search($cycleName, array_keys($levels), true), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($codes as $order => $code) DB::table('school_levels')->insert(['tenant_id' => $tenantId, 'school_cycle_id' => $cycleId, 'name' => $code, 'code' => $code, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_sessions');
        Schema::dropIfExists('group_teacher');
        Schema::dropIfExists('course_teacher');
        Schema::dropIfExists('course_school_level');
        Schema::table('students', fn (Blueprint $table) => $table->dropConstrainedForeignId('training_plan_group_id'));
        Schema::table('training_plan_groups', fn (Blueprint $table) => $table->dropConstrainedForeignId('school_level_id'));
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('type'));
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('school_levels');
        Schema::dropIfExists('school_cycles');
    }
};
