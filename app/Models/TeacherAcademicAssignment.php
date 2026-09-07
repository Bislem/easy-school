<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAcademicAssignment extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            if (! $assignment->school_group_id) {
                return;
            }
            $group = SchoolGroup::findOrFail($assignment->school_group_id);
            if ((int) $group->academic_year_id !== (int) $assignment->academic_year_id
                || (int) $group->school_level_id !== (int) $assignment->school_level_id
                || (int) ($group->school_stream_id ?? 0) !== (int) ($assignment->school_stream_id ?? 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['school_group_id' => 'Le groupe, le niveau et la filière doivent appartenir à la même année scolaire.']);
            }
        });
    }

    protected $fillable = ['academic_year_id', 'teacher_id', 'course_id', 'school_level_id', 'school_stream_id', 'school_group_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SchoolSubject::class, 'course_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(SchoolStream::class, 'school_stream_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SchoolGroup::class, 'school_group_id');
    }
}
