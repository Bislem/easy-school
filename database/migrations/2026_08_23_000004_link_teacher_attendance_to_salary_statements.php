<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_statement_teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_attendance_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['salary_statement_id', 'teacher_attendance_id'], 'salary_teacher_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_statement_teacher_attendances');
    }
};
