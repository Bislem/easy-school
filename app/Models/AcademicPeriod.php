<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected $fillable = ['academic_year_id', 'name', 'number', 'academic_year', 'starts_on', 'ends_on', 'status', 'is_current'];

    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'is_current' => 'boolean'];
    }

    public function timetableSessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
