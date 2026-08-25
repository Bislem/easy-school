<?php

namespace App\Observers;

use App\Models\CourseEnrollment;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use App\Models\SessionAttendance;
use App\Models\StudentPayment;
use App\Models\TeacherAttendance;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanTeacherAccess;
use App\Models\TrainingSession;
use App\Services\NotificationDispatcher;
use Illuminate\Database\Eloquent\Model;

class PortalNotificationObserver
{
    public bool $afterCommit = true;

    public function created(Model $model): void
    {
        if ($model instanceof TrainingPlan) $this->planCreated($model);
        elseif ($model instanceof TrainingPlanTeacherAccess) $this->planAccessCreated($model);
        elseif ($model instanceof TrainingSession) $this->sessionAssigned($model);
        elseif ($model instanceof SessionAttendance) $this->studentAttendance($model);
        elseif ($model instanceof TeacherAttendance) $this->teacherAttendance($model);
        elseif ($model instanceof StudentPayment) $this->studentPayment($model);
        elseif ($model instanceof SalaryStatement) $this->salaryGenerated($model);
        elseif ($model instanceof SalaryPayment) $this->salaryPaid($model);
        elseif ($model instanceof CourseEnrollment) $this->studentAssigned($model, false);
    }

    public function updated(Model $model): void
    {
        if ($model instanceof TrainingPlan && $model->wasChanged('teacher_id')) $this->planCreated($model);
        elseif ($model instanceof TrainingSession && $model->wasChanged('teacher_id')) $this->sessionAssigned($model);
        elseif ($model instanceof TeacherAttendance) $this->teacherAttendance($model);
        elseif ($model instanceof SessionAttendance) $this->studentAttendance($model);
        elseif ($model instanceof CourseEnrollment && $model->wasChanged('training_plan_group_id')) $this->studentAssigned($model, true);
    }

    private function send(int $userId, string $type, string $title, string $message, Model $related, array $data = []): void
    {
        app(NotificationDispatcher::class)->send($userId, $type, $title, $message, $related, $data);
    }

    private function planCreated(TrainingPlan $plan): void
    {
        if ($plan->teacher_id) $this->send($plan->teacher_id, 'planning.created', 'Nouvelle planification', 'La planification « '.$plan->title.' » vous a été attribuée.', $plan, ['url'=>'/admin/planifications/'.$plan->id]);
    }

    private function planAccessCreated(TrainingPlanTeacherAccess $access): void
    {
        if (! $access->is_main) {$plan=$access->trainingPlan;$this->send($access->teacher_id, 'planning.access_granted', 'Accès à une planification', 'Vous avez reçu un accès à la planification « '.$plan->title.' ».',$access,['url'=>'/admin/planifications/'.$plan->id]);}
    }

    private function sessionAssigned(TrainingSession $session): void
    {
        if (! $session->teacher_id) return;$session->loadMissing('group.plan');$this->send($session->teacher_id,'session.assigned','Nouvelle séance assignée','La séance « '.$session->title.' » de '.$session->group?->plan?->title.' vous a été assignée.',$session,['url'=>'/admin/planifications/'.$session->group?->training_plan_id,'starts_at'=>$session->starts_at]);
    }

    private function teacherAttendance(TeacherAttendance $attendance): void
    {
        $attendance->loadMissing('session');$ids=collect([$attendance->scheduled_teacher_id,$attendance->actual_teacher_id])->filter()->unique();foreach($ids as $id)$this->send((int)$id,'attendance.teacher_recorded','Votre présence a été enregistrée','Votre présence pour la séance « '.($attendance->session?->title??'Séance').' » est marquée : '.$attendance->status.'.',$attendance,['status'=>$attendance->status,'url'=>'/portal/attendance']);
    }

    private function studentAttendance(SessionAttendance $attendance): void
    {
        $attendance->loadMissing(['student.parents.user','session']);foreach($attendance->student?->parents?->pluck('user')->filter()->unique('id')??[] as $parent)$this->send($parent->id,'attendance.student_recorded','Présence de votre enfant enregistrée',$attendance->student->full_name.' est marqué(e) « '.$attendance->status.' » pour la séance « '.($attendance->session?->title??'Séance').' ».',$attendance,['status'=>$attendance->status,'url'=>'/portal/children/'.$attendance->student_id]);
    }

    private function studentPayment(StudentPayment $payment): void
    {
        if ($payment->status!=='completed'||(float)$payment->amount<=0) return;$payment->loadMissing('student.parents.user');foreach($payment->student?->parents?->pluck('user')->filter()->unique('id')??[] as $parent)$this->send($parent->id,'payment.student_validated','Paiement validé','Un paiement de '.number_format((float)$payment->amount,2,',',' ').' DA a été validé pour '.$payment->student->full_name.'.',$payment,['url'=>'/portal/children/'.$payment->student_id]);
    }

    private function salaryGenerated(SalaryStatement $statement): void
    {
        $statement->loadMissing('staff');if($statement->staff?->user_id)$this->send($statement->staff->user_id,'salary.generated','Bulletin de salaire généré','Le bulletin '.$statement->reference.' a été généré.',$statement,['url'=>'/portal/payments']);
    }

    private function salaryPaid(SalaryPayment $payment): void
    {
        $payment->loadMissing('staff');if($payment->staff?->user_id)$this->send($payment->staff->user_id,'salary.paid','Paiement salarial enregistré','Un paiement salarial de '.number_format((float)$payment->amount,2,',',' ').' DA a été enregistré.',$payment,['url'=>'/portal/payments']);
    }

    private function studentAssigned(CourseEnrollment $enrollment, bool $moved): void
    {
        if(!$enrollment->student_id||!$enrollment->training_plan_group_id)return;$enrollment->loadMissing(['student.parents.user','trainingPlanGroup.plan']);$student=$enrollment->student;$group=$enrollment->trainingPlanGroup;foreach($student?->parents?->pluck('user')->filter()->unique('id')??[] as $parent)$this->send($parent->id,$moved?'student.group_changed':'student.plan_assigned',$moved?'Groupe modifié':'Nouvelle planification',$student->full_name.($moved?' a changé de groupe et rejoint « ':' a été ajouté(e) à « ').$group->name.' » dans '.$group->plan?->title.'.',$enrollment,['url'=>'/portal/children/'.$student->id]);
    }
}
