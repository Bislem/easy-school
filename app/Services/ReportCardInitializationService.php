<?php
namespace App\Services;
use App\Models\{ReportCard, StudentAcademicEnrollment, TeacherAcademicAssignment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ReportCardInitializationService {
    public function initialize(StudentAcademicEnrollment $enrollment, string $period): ReportCard {
        abort_unless(isset(config('report_cards.periods')[$period]), 422, 'Période académique invalide.');
        $enrollment->loadMissing(['academicYear','level','group','student']);
        $tenantId = $enrollment->tenant_id ?? app(\App\Tenancy\TenantContext::class)->id();
        return DB::transaction(function () use ($enrollment, $period, $tenantId) {
            if (ReportCard::where('tenant_id',$tenantId)->where('student_academic_enrollment_id',$enrollment->id)->where('period_key',$period)->exists()) throw ValidationException::withMessages(['period'=>'Le bulletin existe déjà pour cette inscription et cette période.']);
            $card = ReportCard::create(['tenant_id'=>$tenantId,'academic_year_id'=>$enrollment->academic_year_id,'student_id'=>$enrollment->student_id,'student_academic_enrollment_id'=>$enrollment->id,'school_level_id'=>$enrollment->school_level_id,'school_group_id'=>$enrollment->school_group_id,'period_key'=>$period,'generated_at'=>now()]);
            $subjects = $enrollment->level->subjects()->wherePivot('is_active',true)->get();
            foreach ($subjects as $subject) {
                $assignment = TeacherAcademicAssignment::where('academic_year_id',$enrollment->academic_year_id)->where('course_id',$subject->id)->where('school_level_id',$enrollment->school_level_id)->when($enrollment->school_group_id, fn($q)=>$q->where(fn($q)=>$q->where('school_group_id',$enrollment->school_group_id)->orWhereNull('school_group_id')))->where('is_active',true)->with('teacher:id,name')->first();
                $card->subjects()->create(['subject_id'=>$subject->id,'teacher_id'=>$assignment?->teacher_id,'subject_name'=>$subject->title,'teacher_name'=>$assignment?->teacher?->name,'coefficient'=>$subject->pivot->coefficient ?? 1]);
            }
            return $card->load('subjects');
        });
    }
}
