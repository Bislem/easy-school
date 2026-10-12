<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formation billing now lives exclusively in the financial ledger
 * (financial_accounts / installments / transactions / allocations).
 */
return new class extends Migration
{
    private const COLUMNS = ['formation_price', 'discount_amount', 'adjustment_total', 'final_price', 'total_paid', 'remaining_balance', 'payment_status'];

    public function up(): void
    {
        Schema::dropIfExists('enrollment_financial_adjustments');
        Schema::dropIfExists('student_payments');
        Schema::dropIfExists('student_installments');
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropIndex(['payment_status']);
        });
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropColumn(self::COLUMNS);
        });
    }

    public function down(): void
    {
        (require __DIR__.'/2026_08_20_000003_create_student_finance.php')->up();
        foreach (['student_installments', 'student_payments', 'enrollment_financial_adjustments'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete());
        }
    }
};
