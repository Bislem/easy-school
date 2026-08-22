<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\SessionAttendance;
use App\Models\TrainingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function roster(TrainingSession $session)
    {
        return CourseEnrollment::with('student')
            ->where('training_plan_group_id', $session->training_plan_group_id)
            ->where('status', 'registered')->whereNotNull('student_id')->get();
    }

    public function recordStudents(TrainingSession $session, array $records, int $userId, ?string $correctionReason = null, bool $canCorrectLocked = false): void
    {
        if ($session->attendance_locked_at && ! $canCorrectLocked) {
            throw ValidationException::withMessages(['attendance' => 'Cette feuille est validée et verrouillée.']);
        }
        if ($session->attendance_locked_at && blank($correctionReason)) {
            throw ValidationException::withMessages(['correction_reason' => 'Le motif de correction est obligatoire.']);
        }
        $allowed = $this->roster($session)->keyBy('student_id');
        DB::transaction(function () use ($session, $records, $userId, $correctionReason, $allowed) {
            foreach ($records as $record) {
                $studentId = (int) $record['student_id'];
                if (! $enrollment = $allowed->get($studentId)) abort(403);
                $attendance = SessionAttendance::firstOrNew(['training_session_id'=>$session->id,'student_id'=>$studentId]);
                $old = $attendance->exists ? $attendance->only(['status','is_justified','justification','notes']) : null;
                $values=['course_enrollment_id'=>$enrollment->id,'status'=>$record['status'],'is_justified'=>(bool)($record['is_justified']??$record['status']==='excused'),'justification'=>$record['justification']??null,'notes'=>$record['notes']??null,'recorded_at'=>now(),'recorded_by'=>$userId];
                $attendance->fill($values)->save();
                $attendance->histories()->create(['user_id'=>$userId,'event'=>$old?'updated':'created','old_values'=>$old,'new_values'=>$attendance->only(array_keys($values)),'reason'=>$correctionReason,'occurred_at'=>now()]);
            }
            $session->update(['attendance_status'=>'completed']);
        });
    }

    public function validate(TrainingSession $session, int $userId): void
    {
        if (! $session->attendances()->exists()) throw ValidationException::withMessages(['attendance'=>'Saisissez les présences avant validation.']);
        $session->update(['attendance_status'=>'validated','attendance_locked_at'=>now(),'attendance_locked_by'=>$userId]);
    }

    public function reopen(TrainingSession $session, int $userId, string $reason): void
    {
        $session->attendances->each(fn($attendance)=>$attendance->histories()->create(['user_id'=>$userId,'event'=>'reopened','reason'=>$reason,'occurred_at'=>now()]));
        $session->update(['attendance_status'=>'completed','attendance_locked_at'=>null,'attendance_locked_by'=>null]);
    }
}
