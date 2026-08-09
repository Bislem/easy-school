<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlanGroup extends Model
{
    protected $fillable = ['training_plan_id', 'classroom_id', 'group_number', 'name', 'capacity'];
    protected function casts(): array { return ['group_number' => 'integer', 'capacity' => 'integer']; }
    public function plan(): BelongsTo { return $this->belongsTo(TrainingPlan::class, 'training_plan_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function sessions(): HasMany { return $this->hasMany(TrainingSession::class); }
}
