<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingPlanGroup extends Model
{
    protected $fillable = ['training_plan_id', 'school_level_id', 'academic_period_id', 'classroom_id', 'principal_teacher_id', 'group_number', 'name', 'code', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['group_number' => 'integer', 'capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function defaultClassroom(): BelongsTo
    {
        return $this->classroom();
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function principalTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'principal_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function teachers(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'group_teacher', 'training_plan_group_id', 'teacher_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function timetableSessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class)->where('status', 'registered');
    }
}
