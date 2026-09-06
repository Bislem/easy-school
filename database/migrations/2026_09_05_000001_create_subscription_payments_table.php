<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10)->default('DZD');
            $table->date('paid_at');
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->string('type', 30)->default('subscription');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'paid_at']);
        });

        DB::table('tenants')
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'tenants.subscription_plan_id')
            ->whereNotNull('tenants.payment_proof_path')
            ->select('tenants.id', 'tenants.subscription_plan_id', 'tenants.payment_proof_path', 'tenants.registration_submitted_at', 'subscription_plans.price', 'subscription_plans.currency')
            ->orderBy('tenants.id')
            ->each(function ($tenant) {
                DB::table('subscription_payments')->insert([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $tenant->subscription_plan_id,
                    'amount' => $tenant->price ?? 0,
                    'currency' => $tenant->currency ?? 'DZD',
                    'paid_at' => $tenant->registration_submitted_at ?: now(),
                    'proof_path' => $tenant->payment_proof_path,
                    'type' => 'registration',
                    'notes' => 'Preuve importée depuis la demande d’inscription.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
