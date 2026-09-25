<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_pricing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->foreignId('enrollment_form_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('training_plan_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('total_price', 14, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'course_id', 'enrollment_form_id']);
        });
        Schema::create('formation_pricing_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('formation_pricing_config_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (! Schema::hasTable('financial_accounts')) {
            return;
        }
        DB::table('course_enrollments')->whereNotNull('student_id')->orderBy('id')->each(function ($enrollment): void {
            if (DB::table('financial_accounts')->where('tenant_id', $enrollment->tenant_id)->where('accountable_type', 'App\\Models\\CourseEnrollment')->where('accountable_id', $enrollment->id)->exists()) {
                return;
            }
            $accountId = DB::table('financial_accounts')->insertGetId(['tenant_id' => $enrollment->tenant_id, 'accountable_type' => 'App\\Models\\CourseEnrollment', 'accountable_id' => $enrollment->id, 'domain' => 'formation', 'student_id' => $enrollment->student_id, 'expected_total' => $enrollment->final_price ?? $enrollment->formation_price ?? 0, 'paid_total' => $enrollment->total_paid ?? 0, 'adjustment_total' => 0, 'balance' => $enrollment->remaining_balance ?? 0, 'status' => match ($enrollment->payment_status ?? 'unpaid') {
                'partially_paid' => 'partial',default => $enrollment->payment_status ?? 'unpaid'
            }, 'created_at' => $enrollment->created_at, 'updated_at' => $enrollment->updated_at]);
            $installments = DB::table('student_installments')->where('course_enrollment_id', $enrollment->id)->orderBy('due_date')->get();
            $installmentMap = [];
            if ($installments->isEmpty()) {
                $installmentMap[0] = DB::table('financial_installments')->insertGetId(['tenant_id' => $enrollment->tenant_id, 'financial_account_id' => $accountId, 'source_key' => 'legacy-total', 'label' => 'Prix de la formation', 'amount' => $enrollment->final_price ?? $enrollment->formation_price ?? 0, 'due_date' => $enrollment->registered_at ? substr((string) $enrollment->registered_at, 0, 10) : now()->toDateString(), 'sort_order' => 0, 'status' => match ($enrollment->payment_status ?? 'unpaid') {
                    'partially_paid' => 'partial',default => $enrollment->payment_status ?? 'unpaid'
                }, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($installments as $i => $item) {
                $installmentMap[$item->id] = DB::table('financial_installments')->insertGetId(['tenant_id' => $enrollment->tenant_id, 'financial_account_id' => $accountId, 'source_key' => 'legacy-installment-'.$item->id, 'label' => 'Échéance '.($i + 1), 'amount' => $item->amount, 'due_date' => $item->due_date, 'sort_order' => $i, 'status' => $item->status, 'created_at' => $item->created_at, 'updated_at' => $item->updated_at]);
            }
            DB::table('student_payments')->where('course_enrollment_id', $enrollment->id)->orderBy('id')->each(function ($payment) use ($accountId, $installmentMap, $enrollment): void {
                $type = $payment->status === 'reversal' ? 'reversal' : ((float) $payment->amount < 0 ? 'refund' : 'payment');
                $transactionId = DB::table('financial_transactions')->insertGetId(['tenant_id' => $enrollment->tenant_id, 'financial_account_id' => $accountId, 'reference' => $payment->reference, 'type' => $type, 'amount' => $payment->amount, 'transaction_date' => $payment->payment_date, 'payment_method' => $payment->payment_method, 'notes' => $payment->notes, 'recorded_by' => $payment->recorded_by, 'created_at' => $payment->created_at, 'updated_at' => $payment->updated_at]);
                $installmentId = $installmentMap[$payment->student_installment_id] ?? reset($installmentMap);
                if ($installmentId) {
                    DB::table('financial_allocations')->insert(['tenant_id' => $enrollment->tenant_id, 'financial_transaction_id' => $transactionId, 'financial_installment_id' => $installmentId, 'amount' => $payment->amount, 'created_at' => $payment->created_at, 'updated_at' => $payment->updated_at]);
                }
            });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_pricing_schedule_items');
        Schema::dropIfExists('formation_pricing_configs');
    }
};
