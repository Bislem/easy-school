<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_level_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('school_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'academic_year_id', 'school_level_id']);
        });
        Schema::create('school_fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['school_fee_structure_id', 'code']);
        });
        Schema::create('school_fee_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_fee_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->morphs('accountable');
            $table->string('domain', 30)->index();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('school_fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('expected_total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('adjustment_total', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('status', 30)->default('unpaid')->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'accountable_type', 'accountable_id'], 'financial_account_owner_unique');
        });
        Schema::create('financial_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_fee_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 120);
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->date('due_date')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamps();
            $table->unique(['financial_account_id', 'source_key']);
        });
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('type', 30)->index();
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date')->index();
            $table->string('payment_method', 50)->nullable();
            $table->string('external_reference')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('financial_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_installment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['financial_transaction_id', 'financial_installment_id'], 'financial_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_allocations');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_installments');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('school_fee_schedule_items');
        Schema::dropIfExists('school_fee_components');
        Schema::dropIfExists('school_fee_structures');
    }
};
