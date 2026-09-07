<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->foreignId('academic_period_id')->nullable()->change();
            $table->index(['academic_year_id', 'day', 'start_time', 'end_time'], 'timetable_year_time_idx');
        });
        Schema::table('room_reservations', function (Blueprint $table) {
            $table->foreignId('academic_period_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_sessions', function (Blueprint $table) {
            $table->dropIndex('timetable_year_time_idx');
        });
    }
};
