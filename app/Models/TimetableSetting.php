<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableSetting extends Model
{
    protected $fillable = ['working_days', 'day_starts_at', 'day_ends_at', 'default_session_duration', 'breaks', 'time_slots'];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'default_session_duration' => 'integer', 'breaks' => 'array', 'time_slots' => 'array'];
    }

    public static function defaults(): array
    {
        return ['working_days' => [7, 1, 2, 3, 4], 'day_starts_at' => '08:00', 'day_ends_at' => '17:00', 'default_session_duration' => 60, 'breaks' => [], 'time_slots' => []];
    }
}
