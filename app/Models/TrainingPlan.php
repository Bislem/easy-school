<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlan extends Model
{
    protected $fillable = ['course_id', 'enrollment_form_id', 'teacher_id', 'title', 'status', 'notes'];

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function enrollmentForm(): BelongsTo { return $this->belongsTo(EnrollmentForm::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function groups(): HasMany { return $this->hasMany(TrainingPlanGroup::class); }
}
