<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->unsignedInteger('duration_hours')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->text('prerequisites')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['course_id', 'code']);
        });

        Schema::table('training_plans', function (Blueprint $table) {
            $table->foreignId('course_level_id')->nullable()->after('course_id')->constrained()->restrictOnDelete();
        });

        DB::table('courses')->orderBy('id')->each(function ($course) {
            $levelId = DB::table('course_levels')->insertGetId([
                'course_id' => $course->id,
                'name' => 'Niveau général',
                'code' => 'GENERAL',
                'duration_hours' => $course->duration_hours,
                'price' => $course->price,
                'prerequisites' => $course->prerequisites,
                'is_active' => $course->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('training_plans')->where('course_id', $course->id)->update(['course_level_id' => $levelId]);
        });

        Schema::table('training_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('course_level_id')->nullable(false)->change();
        });

        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('training_plans', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::table('training_plans')->orderBy('id')->each(function ($plan) {
            $courseId = DB::table('course_levels')->where('id', $plan->course_level_id)->value('course_id');
            DB::table('training_plans')->where('id', $plan->id)->update(['course_id' => $courseId]);
        });
        Schema::table('training_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_level_id');
        });
        Schema::dropIfExists('course_levels');
    }
};
