<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('organization_type', 40)->default('private_school')->after('account_type')->index();
        });
        DB::table('demo_requests')->whereNotNull('tenant_id')->whereNotNull('school_type')->get()
            ->each(fn ($request) => DB::table('tenants')->where('id', $request->tenant_id)->update(['organization_type' => $request->school_type]));

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->string('name', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number')->nullable()->after('name');
            $table->string('status', 20)->default('draft')->after('ends_on');
            $table->unique(['academic_year_id', 'number']);
        });
        Schema::table('school_groups', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('legacy_training_plan_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_stream_id')->nullable()->after('school_level_id')->constrained()->nullOnDelete();
            $table->index(['academic_year_id', 'school_level_id']);
        });
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('tenant_id')->constrained()->restrictOnDelete();
        });

        Schema::create('student_academic_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_stream_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->date('enrollment_date');
            $table->unsignedBigInteger('source_registration_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'student_id']);
        });
        Schema::create('teacher_academic_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_stream_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_group_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['academic_year_id', 'teacher_id']);
        });

        DB::table('tenants')->where('organization_type', 'private_school')->orderBy('id')->each(function ($tenant): void {
            $period = DB::table('academic_periods')->where('tenant_id', $tenant->id)->orderByDesc('is_current')->orderBy('starts_on')->first();
            $start = $period?->starts_on ?? now()->startOfYear()->toDateString();
            $end = $period?->ends_on ?? now()->endOfYear()->toDateString();
            $name = $period?->academic_year ?: date('Y', strtotime($start)).'-'.date('Y', strtotime($end));
            $yearId = DB::table('academic_years')->insertGetId(['tenant_id' => $tenant->id, 'name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => 'active', 'notes' => 'Année initiale créée lors de la migration.', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('academic_periods')->where('tenant_id', $tenant->id)->update(['academic_year_id' => $yearId]);
            DB::table('school_groups')->where('tenant_id', $tenant->id)->update(['academic_year_id' => $yearId]);
            DB::table('timetable_sessions')->where('tenant_id', $tenant->id)->update(['academic_year_id' => $yearId]);
            DB::table('students')->where('tenant_id', $tenant->id)->whereNotNull('school_group_id')->orderBy('id')->each(function ($student) use ($tenant, $yearId, $start): void {
                $group = DB::table('school_groups')->where('id', $student->school_group_id)->first();
                if ($group) DB::table('student_academic_enrollments')->insert(['tenant_id' => $tenant->id, 'academic_year_id' => $yearId, 'student_id' => $student->id, 'school_level_id' => $group->school_level_id, 'school_stream_id' => $group->school_stream_id, 'school_group_id' => $group->id, 'status' => 'enrolled', 'enrollment_date' => $student->registration_date ?: $start, 'created_at' => now(), 'updated_at' => now()]);
            });
        });
        Schema::table('school_groups', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique(['academic_year_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_academic_assignments');
        Schema::dropIfExists('student_academic_enrollments');
        Schema::table('timetable_sessions', fn (Blueprint $table) => $table->dropConstrainedForeignId('academic_year_id'));
        Schema::table('school_groups', function (Blueprint $table) { $table->dropUnique(['academic_year_id', 'code']); $table->unique(['tenant_id', 'code']); $table->dropIndex(['academic_year_id', 'school_level_id']); $table->dropConstrainedForeignId('school_stream_id'); $table->dropConstrainedForeignId('academic_year_id'); });
        Schema::table('academic_periods', function (Blueprint $table) { $table->dropUnique(['academic_year_id', 'number']); $table->dropConstrainedForeignId('academic_year_id'); $table->dropColumn(['number', 'status']); });
        Schema::dropIfExists('academic_years');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('organization_type'));
    }
};
