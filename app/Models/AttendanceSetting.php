<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = ['monthly_absence_threshold', 'consecutive_days_threshold'];

    protected function casts(): array
    {
        return ['monthly_absence_threshold' => 'integer', 'consecutive_days_threshold' => 'integer'];
    }

    public static function current(): self
    {
        return self::firstOrCreate([], ['monthly_absence_threshold' => 5, 'consecutive_days_threshold' => 3]);
    }
}
