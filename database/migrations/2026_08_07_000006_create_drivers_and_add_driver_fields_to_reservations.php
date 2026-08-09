<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('driving_license_path');
            $table->string('approval_status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('secondary_driver_id')->nullable()->after('user_id')->constrained('drivers')->nullOnDelete();
            $table->foreignId('requested_driver_id')->nullable()->after('secondary_driver_id')->constrained('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_driver_id');
            $table->dropConstrainedForeignId('secondary_driver_id');
        });

        Schema::dropIfExists('drivers');
    }
};
