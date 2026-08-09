<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('training_plan_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('group_number');
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamps();
            $table->unique(['training_plan_id', 'group_number']);
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_plan_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'starts_at', 'ends_at']);
            $table->index(['teacher_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_plan_groups');
        Schema::dropIfExists('training_plans');
    }
};
