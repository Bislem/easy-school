<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    protected $fillable = ['training_plan_group_id', 'classroom_id', 'teacher_id', 'title', 'starts_at', 'ends_at', 'notes', 'status', 'completed_at', 'attendance_status', 'attendance_locked_at', 'attendance_locked_by'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'completed_at' => 'datetime', 'attendance_locked_at'=>'datetime']; }
    public function group(): BelongsTo { return $this->belongsTo(TrainingPlanGroup::class, 'training_plan_group_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function attendances(): HasMany { return $this->hasMany(SessionAttendance::class); }
    public function teacherAttendance(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(TeacherAttendance::class); }
}
