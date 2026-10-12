<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An e-mail does not identify a student: siblings often share a parent's
 * address, the same address may exist in two schools, and a student may be
 * registered again in a session after a cancellation. Keep lookup indexes only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'email']);
        });
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropUnique(['enrollment_form_id', 'email']);
            $table->index(['enrollment_form_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropIndex(['enrollment_form_id', 'email']);
            $table->unique(['enrollment_form_id', 'email']);
        });
        Schema::table('students', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'email']);
            $table->unique(['tenant_id', 'email']);
        });
    }
};
