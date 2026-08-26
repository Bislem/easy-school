<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('category', 40)->default('illness');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('days');
            $table->string('status', 20)->default('pending');
            $table->boolean('certificate_received')->default(false);
            $table->string('certificate_reference', 150)->nullable();
            $table->date('certificate_issued_at')->nullable();
            $table->string('health_professional', 255)->nullable();
            $table->text('administrative_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('actual_return_date')->nullable();
            $table->boolean('fit_to_return_confirmed')->default(false);
            $table->timestamps();
            $table->index(['staff_id', 'status', 'starts_at']);
        });
        Schema::create('sick_leave_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sick_leave_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sick_leave_events');
        Schema::dropIfExists('sick_leaves');
    }
};
