<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timetable_settings')->orderBy('id')->each(function ($setting): void {
            $days = array_map('intval', json_decode($setting->working_days, true) ?: []);
            $ordered = array_values(array_unique([7, ...$days]));
            DB::table('timetable_settings')->where('id', $setting->id)->update(['working_days' => json_encode($ordered)]);
        });
    }

    public function down(): void {}
};
