<?php

namespace App\Services;

use App\Enums\StudentAcademicEnrollmentStatus;
use App\Enums\StudentAcademicResult;
use App\Models\AcademicYear;
use App\Models\PrivateSchoolInscription;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentAcademicEnrollmentService
{
    public function history(Student $student): Collection
    {
        return $student->academicEnrollments()
            ->with(['academicYear:id,name,start_date,end_date,status', 'level.cycle:id,name', 'stream:id,name', 'group:id,name', 'sourceRegistration:id,status', 'promotedToLevel:id,name'])
            ->orderByDesc(AcademicYear::select('start_date')->whereColumn('academic_years.id', 'student_academic_enrollments.academic_year_id'))
            ->get();
    }

    public function context(Student $student, AcademicYear $year): ?StudentAcademicEnrollment
    {
        return $student->academicEnrollments()->with(['academicYear', 'level.cycle', 'stream', 'group.classroom.site', 'sourceRegistration', 'promotedToLevel'])
            ->where('academic_year_id', $year->id)->first();
    }

    public function isAcademicallyActive(Student $student, AcademicYear $year): bool
    {
        return $this->context($student, $year)?->isAcademicallyActive() ?? false;
    }

    /** @param array<string, mixed> $attributes */
    public function create(Student $student, AcademicYear $year, array $attributes): StudentAcademicEnrollment
    {
        $this->assertPrivateSchoolContext($student, $year);
        if ($student->academicEnrollments()->where('academic_year_id', $year->id)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Cet élève possède déjà une inscription académique pour cette année.']);
        }

        return DB::transaction(fn () => StudentAcademicEnrollment::create([
            ...$attributes, 'student_id' => $student->id, 'academic_year_id' => $year->id,
        ]));
    }

    public function ensureFromInscription(PrivateSchoolInscription $inscription): StudentAcademicEnrollment
    {
        abort_unless($inscription->student_id, 422, 'L’inscription doit être liée à un élève.');
        $this->assertPrivateSchoolContext($inscription->student, $inscription->academicYear);
        $existing = StudentAcademicEnrollment::where('academic_year_id', $inscription->academic_year_id)
            ->where('student_id', $inscription->student_id)->first();
        if ($existing) {
            if (! $existing->source_registration_id) {
                $existing->update(['source_registration_id' => $inscription->id]);
            }

            return $existing;
        }

        return $this->create($inscription->student, $inscription->academicYear, [
            'school_level_id' => $inscription->school_level_id,
            'school_group_id' => null,
            'status' => StudentAcademicEnrollmentStatus::ENROLLED->value,
            'enrollment_date' => now()->toDateString(),
            'source_registration_id' => $inscription->id,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'school_level_id' => ['required', 'integer'], 'school_stream_id' => ['nullable', 'integer'],
            'school_group_id' => ['nullable', 'integer'], 'source_registration_id' => ['nullable', 'integer'],
            'status' => ['required', \Illuminate\Validation\Rule::enum(StudentAcademicEnrollmentStatus::class)],
            'final_average' => ['nullable', 'numeric', 'between:0,20'],
            'final_result' => ['nullable', \Illuminate\Validation\Rule::enum(StudentAcademicResult::class)],
            'promoted_to_school_level_id' => ['nullable', 'integer'], 'enrollment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function assertPrivateSchoolContext(Student $student, AcademicYear $year): void
    {
        $tenant = Tenant::findOrFail($year->tenant_id);
        if ($tenant->organization_type !== 'private_school' || (int) $student->tenant_id !== (int) $year->tenant_id) {
            throw ValidationException::withMessages(['academic_year_id' => 'L’inscription académique est réservée à l’école privée et doit appartenir au même établissement.']);
        }
    }
}
