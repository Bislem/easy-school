<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('school_name', 150);
            $table->string('school_type', 100)->nullable();
            $table->string('contact_name', 150);
            $table->string('contact_role', 100)->nullable();
            $table->string('email');
            $table->string('phone', 50);
            $table->string('address')->nullable();
            $table->string('wilaya', 100);
            $table->string('commune', 100);
            $table->string('website')->nullable();
            $table->unsignedInteger('students_count')->nullable();
            $table->unsignedInteger('teachers_count')->nullable();
            $table->unsignedInteger('staff_count')->nullable();
            $table->unsignedInteger('sites_count')->nullable();
            $table->unsignedTinyInteger('requested_days')->default(7);
            $table->text('needs')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->timestamp('credentials_sent_at')->nullable();
            $table->timestamps();
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('account_type', 20)->default('standard')->after('status')->index();
            $table->timestamp('demo_expires_at')->nullable()->after('account_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['account_type', 'demo_expires_at']));
        Schema::dropIfExists('demo_requests');
    }
};
