<?php

namespace App\Models;

use App\Enums\StudentAcademicEnrollmentStatus;
use App\Enums\StudentAcademicResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class StudentAcademicEnrollment extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected static function booted(): void
    {
        static::saving(function (self $enrollment): void {
            $year = AcademicYear::findOrFail($enrollment->academic_year_id);
            $student = Student::findOrFail($enrollment->student_id);
            if ((int) $year->tenant_id !== (int) $student->tenant_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['student_id' => 'L’élève et l’année scolaire doivent appartenir au même établissement.']);
            }
            $level = SchoolLevel::findOrFail($enrollment->school_level_id);
            if ($enrollment->source_registration_id) {
                $registration = PrivateSchoolInscription::findOrFail($enrollment->source_registration_id);
                if ((int) $registration->academic_year_id !== (int) $enrollment->academic_year_id
                    || (int) $registration->student_id !== (int) $enrollment->student_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['source_registration_id' => 'L’inscription d’origine doit correspondre au même élève et à la même année scolaire.']);
                }
            }
            if ($enrollment->promoted_to_school_level_id) {
                $promotedLevel = SchoolLevel::findOrFail($enrollment->promoted_to_school_level_id);
                if ((int) $promotedLevel->id === (int) $level->id && $enrollment->final_result === StudentAcademicResult::PASSED) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['promoted_to_school_level_id' => 'Le niveau suivant doit être différent du niveau de cette année.']);
                }
            }
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

    protected $fillable = ['academic_year_id', 'student_id', 'school_level_id', 'school_stream_id', 'school_group_id', 'status', 'final_average', 'final_result', 'promoted_to_school_level_id', 'enrollment_date', 'source_registration_id', 'notes'];

    protected function casts(): array
    {
        return ['status' => StudentAcademicEnrollmentStatus::class, 'final_result' => StudentAcademicResult::class, 'final_average' => 'decimal:2', 'enrollment_date' => 'date:Y-m-d'];
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

    public function sourceRegistration(): BelongsTo
    {
        return $this->belongsTo(PrivateSchoolInscription::class, 'source_registration_id');
    }

    public function promotedToLevel(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'promoted_to_school_level_id');
    }

    public function financialAccount(): MorphOne
    {
        return $this->morphOne(FinancialAccount::class, 'accountable');
    }

    public function isAcademicallyActive(): bool
    {
        return $this->status === StudentAcademicEnrollmentStatus::ENROLLED;
    }
}
