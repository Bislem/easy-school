<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enrollment_forms')) {
            Schema::create('enrollment_forms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained()->restrictOnDelete();
                $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('public_token')->unique();
                $table->string('title');
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedInteger('min_students')->default(1);
                $table->unsignedInteger('max_students');
                $table->unsignedInteger('groups_count')->default(1);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('course_enrollments')) {
            Schema::create('course_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enrollment_form_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email');
                $table->string('phone', 50);
                $table->date('birth_date')->nullable();
                $table->uuid('confirmation_token')->unique();
                $table->timestamp('confirmed_at')->nullable()->index();
                $table->unsignedInteger('group_number')->nullable();
                $table->timestamps();
                $table->unique(['enrollment_form_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        // This migration only repairs missing tables and must not remove existing data.
    }
};
