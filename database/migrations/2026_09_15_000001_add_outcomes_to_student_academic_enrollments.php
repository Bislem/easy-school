<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->decimal('final_average', 5, 2)->nullable()->after('status');
            $table->string('final_result', 20)->nullable()->index()->after('final_average');
            $table->foreignId('promoted_to_school_level_id')->nullable()->after('final_result')->constrained('school_levels')->nullOnDelete();
            $table->foreign('source_registration_id', 'student_academic_enrollment_source_fk')->references('id')->on('private_school_inscriptions')->restrictOnDelete();
            $table->unique('source_registration_id', 'student_academic_enrollment_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_academic_enrollments', function (Blueprint $table) {
            $table->dropUnique('student_academic_enrollment_source_unique');
            $table->dropForeign('student_academic_enrollment_source_fk');
            $table->dropConstrainedForeignId('promoted_to_school_level_id');
            $table->dropColumn(['final_average', 'final_result']);
        });
    }
};
