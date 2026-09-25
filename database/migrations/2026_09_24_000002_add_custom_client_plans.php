<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->foreignId('owner_tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->boolean('is_custom')->default(false)->after('features')->index();
            $table->index(['owner_tenant_id', 'is_active'], 'subscription_plans_owner_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropIndex('subscription_plans_owner_active_idx');
            $table->dropConstrainedForeignId('owner_tenant_id');
            $table->dropColumn('is_custom');
        });
    }
};
