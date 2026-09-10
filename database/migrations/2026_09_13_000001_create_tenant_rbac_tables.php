<?php

use App\Services\DefaultTenantRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('system_key', 80)->nullable();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'system_key']);
            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->timestamps();
            $table->primary(['role_id', 'user_id']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'user_id']);
        });
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('role_id');
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
            $table->foreign(['tenant_id', 'role_id'])->references(['tenant_id', 'id'])->on('roles')->cascadeOnDelete();
            $table->index(['tenant_id', 'role_id']);
        });

        app(DefaultTenantRoles::class)->provisionAllTenants();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('role_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'id']));
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
