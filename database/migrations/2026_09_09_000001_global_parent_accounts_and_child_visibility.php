<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('last_name', 100)->nullable()->after('first_name');
            $table->timestamp('temporary_password_expires_at')->nullable()->after('password');
        });

        DB::table('parents')->orderBy('id')->each(function ($parent): void {
            DB::table('users')->where('id', $parent->user_id)->update([
                'first_name' => $parent->first_name,
                'last_name' => $parent->last_name,
            ]);
        });
        Schema::table('parents', function (Blueprint $table) {
            $table->dropUnique('parents_user_id_unique');
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::table('parent_student', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('parent_student', fn (Blueprint $table) => $table->dropColumn('is_visible'));
        Schema::table('parents', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->unique('user_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'temporary_password_expires_at']);
        });
    }
};
