<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_attendances', function (Blueprint $table) {
            $table->time('arrival_time')->nullable()->after('status');
            $table->time('departure_time')->nullable()->after('arrival_time');
            $table->boolean('is_justified')->default(false)->after('departure_time');
            $table->text('justification')->nullable()->after('is_justified');
        });
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->string('attendance_status')->default('pending')->index();
            $table->timestamp('attendance_locked_at')->nullable();
            $table->foreignId('attendance_locked_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actual_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->boolean('is_justified')->default(false);
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('attendance_date')->index();
            $table->string('status')->index();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->boolean('is_justified')->default(false);
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'attendance_date']);
        });
        Schema::create('attendance_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('attendance');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_histories');
        Schema::dropIfExists('employee_attendances');
        Schema::dropIfExists('teacher_attendances');
        Schema::table('training_sessions', fn (Blueprint $table) => $table->dropColumn(['attendance_status', 'attendance_locked_at', 'attendance_locked_by']));
        Schema::table('session_attendances', fn (Blueprint $table) => $table->dropColumn(['arrival_time', 'departure_time', 'is_justified', 'justification']));
    }
};
