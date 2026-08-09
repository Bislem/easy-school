<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingSession extends Model
{
    protected $fillable = ['training_plan_group_id', 'classroom_id', 'teacher_id', 'title', 'starts_at', 'ends_at', 'notes'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function group(): BelongsTo { return $this->belongsTo(TrainingPlanGroup::class, 'training_plan_group_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
