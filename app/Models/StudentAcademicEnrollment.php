<?php

namespace App\Models;

use App\Enums\StudentAcademicEnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAcademicEnrollment extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected static function booted(): void
    {
        static::saving(function (self $enrollment): void {
            if (! $enrollment->school_group_id) {
                return;
            }
            $group = SchoolGroup::findOrFail($enrollment->school_group_id);
            if ((int) $group->academic_year_id !== (int) $enrollment->academic_year_id
                || (int) $group->school_level_id !== (int) $enrollment->school_level_id
                || (int) ($group->school_stream_id ?? 0) !== (int) ($enrollment->school_stream_id ?? 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['school_group_id' => 'Le groupe, le niveau et la filière doivent appartenir à la même année scolaire.']);
            }
        });
    }

    protected $fillable = ['academic_year_id', 'student_id', 'school_level_id', 'school_stream_id', 'school_group_id', 'status', 'enrollment_date', 'source_registration_id', 'notes'];

    protected function casts(): array
    {
        return ['status' => StudentAcademicEnrollmentStatus::class, 'enrollment_date' => 'date:Y-m-d'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
