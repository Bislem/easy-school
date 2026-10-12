<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'enrollment_form_id', 'training_plan_group_id', 'student_id', 'status', 'first_name', 'last_name', 'email',
        'phone', 'parent_phone', 'birth_date', 'level', 'confirmation_token', 'confirmed_at', 'notes',
        'contacted_at', 'approved_at', 'registered_at', 'rejected_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'confirmed_at' => 'datetime',
            'status' => ApplicationStatus::class, 'contacted_at' => 'datetime', 'approved_at' => 'datetime',
            'registered_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(EnrollmentForm::class, 'enrollment_form_id');
    }

    public function trainingPlanGroup(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanGroup::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EnrollmentHistory::class)->latest();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class)->latest('issue_date');
    }

    public function financialAccount(): MorphOne
    {
        return $this->morphOne(FinancialAccount::class, 'accountable');
    }
}
