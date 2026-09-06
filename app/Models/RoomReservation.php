<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomReservation extends Model
{
    protected $fillable = ['timetable_session_id', 'classroom_id', 'academic_period_id', 'day', 'effective_date', 'start_time', 'end_time', 'status'];
    protected function casts(): array { return ['day' => 'integer', 'effective_date' => 'date:Y-m-d']; }
    public function session(): BelongsTo { return $this->belongsTo(TimetableSession::class, 'timetable_session_id'); }
    public function room(): BelongsTo { return $this->belongsTo(Classroom::class, 'classroom_id'); }
    public function academicPeriod(): BelongsTo { return $this->belongsTo(AcademicPeriod::class); }
}
