<?php

namespace App\Models;

use App\Enums\TimetableRecurrence;
use App\Enums\TimetableSessionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TimetableSession extends Model
{
    protected $fillable = ['training_plan_group_id', 'course_id', 'teacher_id', 'classroom_id', 'academic_period_id', 'series_id', 'parent_session_id', 'day', 'effective_date', 'start_time', 'end_time', 'recurrence', 'change_type', 'notes', 'status'];
    protected function casts(): array { return ['day' => 'integer', 'effective_date' => 'date:Y-m-d', 'recurrence' => TimetableRecurrence::class, 'status' => TimetableSessionStatus::class]; }
    public function group(): BelongsTo { return $this->belongsTo(TrainingPlanGroup::class, 'training_plan_group_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Course::class, 'course_id'); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function room(): BelongsTo { return $this->belongsTo(Classroom::class, 'classroom_id'); }
    public function academicPeriod(): BelongsTo { return $this->belongsTo(AcademicPeriod::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_session_id'); }
    public function exceptions(): HasMany { return $this->hasMany(self::class, 'parent_session_id'); }
    public function reservation(): HasOne { return $this->hasOne(RoomReservation::class); }
}
