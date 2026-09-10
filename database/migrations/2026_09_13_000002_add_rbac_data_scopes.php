<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('role_user', fn (Blueprint $table) => $table->string('data_scope', 16)->default('tenant')->after('user_id'));
        Schema::create('school_site_user', function (Blueprint $table) {
            $table->foreignId('tenant_id');
            $table->foreignId('school_site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id');
            $table->primary(['school_site_id', 'user_id']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->index(['tenant_id', 'user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('school_site_user');
        Schema::table('role_user', fn (Blueprint $table) => $table->dropColumn('data_scope'));
    }
};
