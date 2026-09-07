<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->boolean('is_available')->default(true)->after('is_active')->index();
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->json('required_room_types')->nullable()->after('prerequisites');
        });
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->after('academic_period_id')->index();
            $table->date('effective_date')->nullable()->after('day')->index();
            $table->foreignId('parent_session_id')->nullable()->after('series_id')->constrained('timetable_sessions')->nullOnDelete();
            $table->string('change_type', 30)->nullable()->after('recurrence');
            $table->index(['effective_date', 'start_time', 'end_time'], 'timetable_date_time_idx');
        });

        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('timetable_session_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day');
            $table->date('effective_date')->nullable()->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status', 20)->default('reserved')->index();
            $table->timestamps();
            $table->index(['classroom_id', 'day', 'start_time', 'end_time'], 'reservation_room_time_idx');
        });

        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->index(['teacher_id', 'day', 'start_time', 'end_time'], 'teacher_availability_idx');
        });

        Schema::create('teacher_unavailable_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->index(['teacher_id', 'starts_at', 'ends_at'], 'teacher_unavailable_idx');
        });

        Schema::create('timetable_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('working_days');
            $table->time('day_starts_at');
            $table->time('day_ends_at');
            $table->unsignedSmallInteger('default_session_duration')->default(60);
            $table->json('breaks')->nullable();
            $table->json('time_slots')->nullable();
            $table->timestamps();
        });
        foreach (DB::table('tenants')->pluck('id') as $tenantId) DB::table('timetable_settings')->insert([
            'tenant_id' => $tenantId, 'working_days' => json_encode([7, 1, 2, 3, 4]),
            'day_starts_at' => '08:00', 'day_ends_at' => '17:00', 'default_session_duration' => 60,
            'breaks' => json_encode([]), 'time_slots' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_settings');
        Schema::dropIfExists('teacher_unavailable_periods');
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('room_reservations');
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_session_id');
            $table->dropColumn(['series_id', 'effective_date', 'change_type']);
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('required_room_types'));
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('is_available'));
    }
};
