<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->decimal('formation_price', 14, 2)->nullable()->after('notes');
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('adjustment_total', 14, 2)->default(0);
            $table->decimal('final_price', 14, 2)->nullable();
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('remaining_balance', 14, 2)->nullable();
            $table->string('payment_status')->default('unpaid')->index();
        });

        Schema::create('student_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('due_date')->index();
            $table->string('status')->default('pending')->index();
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('student_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date')->index();
            $table->string('payment_method');
            $table->string('status')->default('completed')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_payment_id')->nullable()->constrained('student_payments')->restrictOnDelete();
            $table->decimal('previous_balance', 14, 2);
            $table->decimal('remaining_balance', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollment_financial_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('course_enrollments')->join('enrollment_forms', 'course_enrollments.enrollment_form_id', '=', 'enrollment_forms.id')->join('courses', 'enrollment_forms.course_id', '=', 'courses.id')->select('course_enrollments.id', 'courses.price')->orderBy('course_enrollments.id')->each(function ($row) {
            DB::table('course_enrollments')->where('id', $row->id)->update(['formation_price' => $row->price, 'final_price' => $row->price, 'remaining_balance' => $row->price]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_financial_adjustments');
        Schema::dropIfExists('student_payments');
        Schema::dropIfExists('student_installments');
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropColumn(['formation_price', 'discount_amount', 'adjustment_total', 'final_price', 'total_paid', 'remaining_balance', 'payment_status']));
    }
};
