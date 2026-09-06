<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('role', 30);
            $table->foreignId('parent_id')->nullable()->constrained('parents')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'tenant_id', 'role']);
            $table->index(['user_id', 'is_active']);
        });

        $now = now();
        DB::table('users')->where('role', 'parent')->orderBy('id')->each(function ($user) use ($now) {
            DB::table('mobile_memberships')->insertOrIgnore([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'parent_id' => DB::table('parents')->where('user_id', $user->id)->value('id'),
                'is_active' => $user->is_active && $user->can_login,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void { Schema::dropIfExists('mobile_memberships'); }
};
