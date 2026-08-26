<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('social_security_number', 100)->nullable()->unique()->after('employee_code');
            $table->string('gender', 20)->nullable()->after('birth_date');
            $table->string('place_of_birth')->nullable()->after('birth_date');
            $table->string('nationality', 100)->nullable()->after('place_of_birth');
            $table->string('marital_status', 30)->nullable()->after('nationality');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_relationship', 100)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_relationship');
            $table->string('bank_account', 150)->nullable()->after('emergency_contact_phone');
            $table->decimal('leave_opening_balance', 7, 2)->nullable()->after('hire_date');
            $table->date('leave_balance_as_of')->nullable()->after('leave_opening_balance');
            $table->text('leave_balance_note')->nullable()->after('leave_balance_as_of');
        });

        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('previous_balance', 7, 2)->nullable();
            $table->date('previous_as_of')->nullable();
            $table->decimal('new_balance', 7, 2)->nullable();
            $table->date('new_as_of')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_adjustments');
        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn(['social_security_number', 'gender', 'place_of_birth', 'nationality', 'marital_status', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'bank_account', 'leave_opening_balance', 'leave_balance_as_of', 'leave_balance_note']));
    }
};
