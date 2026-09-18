<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $fillable = ['tenant_id', 'academic_year_id', 'academic_period_id', 'school_level_id', 'subject_id', 'teacher_id', 'name', 'assessment_type', 'assessment_date', 'maximum_grade', 'weight', 'status', 'description', 'completed_by', 'completed_at', 'published_at', 'published_by', 'locked_by', 'locked_at', 'reopened_by', 'reopened_at', 'reopen_reason'];

    protected function casts(): array
    {
        return ['assessment_date' => 'date:Y-m-d', 'maximum_grade' => 'decimal:2', 'weight' => 'decimal:2', 'completed_at' => 'datetime', 'published_at' => 'datetime', 'locked_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function year(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id'); }
    public function period(): BelongsTo { return $this->belongsTo(AcademicPeriod::class, 'academic_period_id'); }
    public function level(): BelongsTo { return $this->belongsTo(SchoolLevel::class, 'school_level_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(SchoolSubject::class, 'subject_id'); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function groups(): BelongsToMany { return $this->belongsToMany(SchoolGroup::class, 'assessment_group')->withPivot('tenant_id'); }
    public function grades(): HasMany { return $this->hasMany(Grade::class); }
}
