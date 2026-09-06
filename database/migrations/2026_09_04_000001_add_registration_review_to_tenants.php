<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('subscription_plan_id');
            $table->timestamp('registration_submitted_at')->nullable()->after('payment_proof_path');
            $table->timestamp('registration_reviewed_at')->nullable()->after('registration_submitted_at');
            $table->text('registration_rejection_reason')->nullable()->after('registration_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'payment_proof_path',
                'registration_submitted_at',
                'registration_reviewed_at',
                'registration_rejection_reason',
            ]);
        });
    }
};
