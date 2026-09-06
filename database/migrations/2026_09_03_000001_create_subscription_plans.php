<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('DZD');
            $table->string('billing_period', 20)->default('monthly');
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_teachers')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->unsignedInteger('max_sites')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_courses')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('status')->constrained('subscription_plans')->nullOnDelete();
            $table->timestamp('plan_started_at')->nullable();
            $table->timestamp('plan_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn(['plan_started_at', 'plan_expires_at']);
        });
        Schema::dropIfExists('subscription_plans');
    }
};
