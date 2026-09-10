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
            $table->unsignedBigInteger('storage_used_bytes')->default(0)->after('settings');
            $table->unsignedBigInteger('storage_limit_bytes')->nullable()->after('storage_used_bytes');
        });
        Schema::create('tenant_stored_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 100);
            $table->string('storage_key', 1000);
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type')->nullable();
            $table->string('category', 50)->index();
            $table->string('module', 100)->nullable()->index();
            $table->string('related_entity_type')->nullable();
            $table->unsignedBigInteger('related_entity_id')->nullable();
            $table->timestamps();
            $table->unique(['disk', 'storage_key']);
            $table->index(['tenant_id', 'category']);
            $table->index(['related_entity_type', 'related_entity_id']);
        });
        DB::table('tenants')->whereNotNull('subscription_plan_id')->update([
            'storage_limit_bytes' => DB::raw('(select storage_mb * 1048576 from subscription_plans where subscription_plans.id = tenants.subscription_plan_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_stored_files');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['storage_used_bytes', 'storage_limit_bytes']));
    }
};
