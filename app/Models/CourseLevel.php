<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLevel extends Model
{
    protected $fillable = ['course_id', 'name', 'code', 'duration_hours', 'price', 'prerequisites', 'is_active'];

    protected function casts(): array
    {
        return ['duration_hours' => 'integer', 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function trainingPlans(): HasMany { return $this->hasMany(TrainingPlan::class); }
}
