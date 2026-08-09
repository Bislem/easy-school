<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('role');
            $table->boolean('can_login')->default(true)->after('is_active')->index();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->date('salary_period')->nullable()->after('expense_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('salary_period');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['job_title', 'can_login']));
    }
};
