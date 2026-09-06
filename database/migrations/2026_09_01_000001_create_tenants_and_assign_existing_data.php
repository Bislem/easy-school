<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users', 'students', 'parents', 'staff', 'employee_types', 'school_sites',
        'classrooms', 'courses', 'course_levels', 'enrollment_forms', 'course_enrollments',
        'enrollment_histories', 'enrollment_financial_adjustments', 'training_plans',
        'training_plan_groups', 'training_sessions', 'training_plan_teacher_accesses',
        'session_attendances', 'teacher_attendances', 'employee_attendances',
        'attendance_histories', 'student_histories', 'student_observations',
        'student_installments', 'student_payments', 'annual_leaves', 'annual_leave_events',
        'sick_leaves', 'sick_leave_events', 'leave_balance_adjustments', 'employee_documents',
        'employee_hr_records', 'employee_hr_record_events', 'salary_configurations',
        'salary_statements', 'salary_adjustments', 'salary_payments', 'expenses',
        'badge_templates', 'badges', 'certificates', 'portal_notifications', 'fcm_tokens',
        'company_settings', 'audit_logs', 'files', 'temp_files', 'parent_student',
        'salary_statement_employee_attendances', 'salary_statement_teacher_attendances',
        'cars', 'drivers', 'reservations', 'payments', 'fuel_tank_records', 'tickets', 'messages',
    ];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('wilaya', 100)->nullable();
            $table->string('commune', 100)->nullable();
            $table->string('status')->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $defaultTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Default School', 'slug' => 'default-school', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            });
            DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
            }
        }
        Schema::dropIfExists('tenants');
    }
};
