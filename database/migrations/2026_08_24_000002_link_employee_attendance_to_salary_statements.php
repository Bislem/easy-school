<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_statement_employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_attendance_id')->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_statement_employee_attendances');
    }
};
