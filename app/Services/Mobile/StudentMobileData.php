<?php

namespace App\Services\Mobile;

use App\Http\Resources\Mobile\V1\AttendanceResource;
use App\Http\Resources\Mobile\V1\EnrollmentResource;
use App\Http\Resources\Mobile\V1\SessionResource;
use App\Models\Student;
use App\Models\TrainingSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StudentMobileData
{
    public function enrollments(Student $student): AnonymousResourceCollection
    {
        return EnrollmentResource::collection($student->enrollments()->where('status', 'registered')->with(['form.course', 'trainingPlanGroup.plan.course'])->latest()->paginate(15));
    }

    public function sessions(Student $student): AnonymousResourceCollection
    {
        $groupIds = $student->enrollments()->where('status', 'registered')->pluck('training_plan_group_id')->filter();
        return SessionResource::collection(TrainingSession::with(['group.plan.course', 'classroom.site', 'teacher:id,name'])->whereIn('training_plan_group_id', $groupIds)->orderBy('starts_at')->paginate(20));
    }

    public function attendance(Student $student): AnonymousResourceCollection
    {
        return AttendanceResource::collection($student->attendances()->with(['session.group.plan.course', 'session.classroom.site', 'session.teacher:id,name'])->latest('recorded_at')->paginate(30));
    }

    public function observations(Student $student): AnonymousResourceCollection
    {
        return \App\Http\Resources\Mobile\V1\ObservationResource::collection($student->observations()->whereNull('parent_id')->with(['author:id,name,role', 'replies.author:id,name,role'])->latest()->paginate(20));
    }
}
