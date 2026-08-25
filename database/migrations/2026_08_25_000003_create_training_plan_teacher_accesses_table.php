<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plan_teacher_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_main')->default(false);
            $table->boolean('can_manage_groups')->default(false);
            $table->boolean('can_add_sessions')->default(false);
            $table->boolean('can_record_attendance')->default(false);
            $table->timestamps();
            $table->unique(['training_plan_id', 'teacher_id']);
        });

        $now = now();
        DB::table('training_plans')->whereNotNull('teacher_id')->orderBy('id')->each(function ($plan) use ($now) {
            DB::table('training_plan_teacher_accesses')->insert([
                'training_plan_id' => $plan->id,
                'teacher_id' => $plan->teacher_id,
                'is_main' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_plan_teacher_accesses');
    }
};
