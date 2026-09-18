<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('course_school_level', 'coefficient')) {
            Schema::table('course_school_level', fn (Blueprint $table) => $table->decimal('coefficient', 6, 2)->default(1));
        }
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_academic_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('period_key', 40);
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('general_average', 5, 2)->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('total_students')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->text('administration_comment')->nullable();
            $table->unsignedInteger('absences_count')->nullable();
            $table->unsignedInteger('late_count')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'student_academic_enrollment_id', 'period_key'], 'report_cards_enrollment_period_unique');
        });
        Schema::create('report_card_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('report_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_name');
            $table->string('teacher_name')->nullable();
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->decimal('average', 5, 2)->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('min_average', 5, 2)->nullable();
            $table->decimal('max_average', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('appreciation')->nullable();
            $table->timestamps();
            $table->unique(['report_card_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_subjects');
        Schema::dropIfExists('report_cards');
    }
};
