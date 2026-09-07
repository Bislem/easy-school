<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->date('end_date')->nullable()->index();
            $table->foreignId('timetable_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('person_type', 10)->index();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('minutes_late')->nullable();
            $table->string('reason')->nullable();
            $table->text('justification')->nullable();
            $table->timestamp('justified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id', 'student_id', 'date'], 'attendance_student_date_idx');
            $table->index(['academic_year_id', 'teacher_id', 'date', 'end_date'], 'attendance_teacher_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_exceptions');
    }
};
