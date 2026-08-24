<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TrainingPlan extends Model
{
    protected $fillable = ['course_level_id', 'enrollment_form_id', 'teacher_id', 'title', 'status', 'notes'];

    public function level(): BelongsTo { return $this->belongsTo(CourseLevel::class, 'course_level_id'); }
    public function course(): HasOneThrough
    {
        return $this->hasOneThrough(Course::class, CourseLevel::class, 'id', 'id', 'course_level_id', 'course_id');
    }
    public function enrollmentForm(): BelongsTo { return $this->belongsTo(EnrollmentForm::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function groups(): HasMany { return $this->hasMany(TrainingPlanGroup::class); }
}
