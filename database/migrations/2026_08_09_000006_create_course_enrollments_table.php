<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};
