<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('driving_license_number')->nullable()->unique();
            $table->date('driving_license_delivered_at')->nullable();
            $table->string('driving_license_authority')->nullable();
            $table->string('driving_license_path')->nullable();
            $table->string('approval_status')->default('approved')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropUnique(['driving_license_number']);
            $table->dropColumn([
                'birth_date', 'driving_license_number', 'driving_license_delivered_at',
                'driving_license_authority', 'driving_license_path', 'approval_status',
                'rejection_reason', 'approved_at', 'approved_by',
            ]);
        });
    }
};
