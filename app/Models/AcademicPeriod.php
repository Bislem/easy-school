<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    protected $fillable = ['name', 'academic_year', 'starts_on', 'ends_on', 'is_current'];
    protected function casts(): array { return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'is_current' => 'boolean']; }
    public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); }
}
