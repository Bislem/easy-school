<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPlanTeacherAccess extends Model
{
    protected $fillable = [
        'teacher_id', 'is_main', 'can_manage_groups', 'can_add_sessions', 'can_record_attendance',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'can_manage_groups' => 'boolean',
        'can_add_sessions' => 'boolean',
        'can_record_attendance' => 'boolean',
    ];

    public function trainingPlan(): BelongsTo { return $this->belongsTo(TrainingPlan::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
