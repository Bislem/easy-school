<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolGroup extends Model
{
    use Concerns\GuardsAcademicYearWrites;
    protected $fillable = ['academic_year_id', 'school_level_id', 'school_stream_id', 'academic_period_id', 'classroom_id', 'principal_teacher_id', 'name', 'code', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean'];
    }

    public function level(): BelongsTo { return $this->belongsTo(SchoolLevel::class, 'school_level_id'); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function stream(): BelongsTo { return $this->belongsTo(SchoolStream::class, 'school_stream_id'); }
    public function academicEnrollments(): HasMany { return $this->hasMany(StudentAcademicEnrollment::class); }
    public function academicPeriod(): BelongsTo { return $this->belongsTo(AcademicPeriod::class); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function defaultClassroom(): BelongsTo { return $this->classroom(); }
    public function principalTeacher(): BelongsTo { return $this->belongsTo(User::class, 'principal_teacher_id'); }
    public function students(): HasMany { return $this->hasMany(Student::class); }
    public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); }

    public function teachers(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'school_group_teacher', 'school_group_id', 'teacher_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }
}
