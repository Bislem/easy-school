<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicYearCalendarEvent extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'type', 'starts_on', 'ends_on',
        'applies_to', 'is_paid_for_teachers', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_paid_for_teachers' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
