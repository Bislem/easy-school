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
        if ($model instanceof TrainingPlan) {
            $this->planCreated($model);
        } elseif ($model instanceof TrainingPlanTeacherAccess) {
            $this->planAccessCreated($model);
        } elseif ($model instanceof TrainingSession) {
            $this->sessionAssigned($model);
        } elseif ($model instanceof SessionAttendance) {
            $this->studentAttendance($model);
        } elseif ($model instanceof TeacherAttendance) {
            $this->teacherAttendance($model);
        } elseif ($model instanceof StudentPayment) {
            $this->studentPayment($model);
        } elseif ($model instanceof SalaryStatement) {
            $this->salaryGenerated($model);
        } elseif ($model instanceof SalaryPayment) {
            $this->salaryPaid($model);
        } elseif ($model instanceof CourseEnrollment) {
            $this->studentAssigned($model, false);
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof TrainingPlan && $model->wasChanged('teacher_id')) {
            $this->planCreated($model);
        } elseif ($model instanceof TrainingSession && $model->wasChanged('teacher_id')) {
            $this->sessionAssigned($model);
        } elseif ($model instanceof TeacherAttendance) {
            $this->teacherAttendance($model);
        } elseif ($model instanceof SessionAttendance) {
            $this->studentAttendance($model);
        } elseif ($model instanceof CourseEnrollment && $model->wasChanged('training_plan_group_id')) {
            $this->studentAssigned($model, true);
        }
    }

    private function send(int $userId, string $type, string $title, string $message, Model $related, array $data = []): void
    {
        app(NotificationDispatcher::class)->send($userId, $type, $title, $message, $related, $data);
    }

    private function planCreated(TrainingPlan $plan): void
    {
        if (! $plan->teacher_id) {
            return;
        }

        $plan->loadMissing(['course', 'level', 'groups']);
        $details = collect([
            $plan->course?->title,
            $plan->level?->name ? 'Niveau '.$plan->level->name : null,
            $plan->groups->isNotEmpty() ? $plan->groups->count().' groupe(s)' : null,
        ])->filter()->implode(' • ');

        $this->send($plan->teacher_id, 'planning.created', 'Planification attribuée : '.$plan->title,
            $details ?: 'Cette planification vous a été attribuée.', $plan,
            ['url' => '/admin/planifications/'.$plan->id, 'plan' => $plan->title]);
    }

    private function planAccessCreated(TrainingPlanTeacherAccess $access): void
    {
        if ($access->is_main) {
            return;
        }

        $access->loadMissing('trainingPlan.course');
        $plan = $access->trainingPlan;
        $permissions = collect([
            $access->can_manage_groups ? 'gestion des groupes' : null,
            $access->can_add_sessions ? 'ajout de séances' : null,
            $access->can_record_attendance ? 'saisie des présences' : null,
        ])->filter()->implode(', ');
        $body = collect([$plan?->course?->title, $permissions ? 'Accès : '.$permissions : 'Accès en consultation'])->filter()->implode(' • ');

        $this->send($access->teacher_id, 'planning.access_granted', 'Accès accordé : '.($plan?->title ?? 'Planification'),
            $body, $access, ['url' => '/admin/planifications/'.$plan?->id]);
    }

    private function sessionAssigned(TrainingSession $session): void
    {
        if (! $session->teacher_id) {
            return;
        }

        $session->loadMissing(['group.plan', 'classroom']);
        $body = collect([
            $session->group?->plan?->title,
            $session->group?->name,
            $this->dateTime($session->starts_at),
            $session->classroom?->name ? 'Salle '.$session->classroom->name : null,
        ])->filter()->implode(' • ');

        $this->send($session->teacher_id, 'session.assigned', 'Séance assignée : '.$session->title, $body, $session, [
            'url' => '/admin/planifications/'.$session->group?->training_plan_id,
            'starts_at' => $session->starts_at,
            'group' => $session->group?->name,
            'classroom' => $session->classroom?->name,
        ]);
    }

    private function teacherAttendance(TeacherAttendance $attendance): void
    {
        $attendance->loadMissing('session.group.plan');
        $status = $this->attendanceStatus($attendance->status);
        $body = collect([
            $attendance->session?->title ?? 'Séance',
            $attendance->session?->group?->plan?->title,
            $this->dateTime($attendance->session?->starts_at),
            $attendance->worked_minutes ? $attendance->worked_minutes.' min travaillées' : null,
        ])->filter()->implode(' • ');
        $ids = collect([$attendance->scheduled_teacher_id, $attendance->actual_teacher_id])->filter()->unique();
        foreach ($ids as $id) {
            $this->send((int) $id, 'attendance.teacher_recorded', 'Présence enregistrée : '.$status,
                $body, $attendance, ['status' => $attendance->status, 'url' => '/portal/attendance']);
        }
    }

    private function studentAttendance(SessionAttendance $attendance): void
    {
        $attendance->loadMissing(['student.parents.user', 'session.group.plan']);
        $student = $attendance->student;
        $status = $this->attendanceStatus($attendance->status);
        $body = collect([
            $attendance->session?->title ?? 'Séance',
            $attendance->session?->group?->plan?->title,
            $this->dateTime($attendance->session?->starts_at),
            $attendance->is_justified ? 'Absence justifiée' : null,
        ])->filter()->implode(' • ');
        foreach ($student?->parents?->pluck('user')->filter()->unique('id') ?? [] as $parent) {
            $this->send($parent->id, 'attendance.student_recorded', $student->full_name.' : '.$status,
                $body, $attendance, ['status' => $attendance->status, 'url' => '/portal/children/'.$attendance->student_id]);
        }
    }

    private function studentPayment(StudentPayment $payment): void
    {
        if ($payment->status !== 'completed' || (float) $payment->amount <= 0) {
            return;
        }

        $payment->loadMissing(['student.parents.user', 'enrollment.trainingPlanGroup.plan.course', 'enrollment.form.course']);
        $student = $payment->student;
        $course = $payment->enrollment?->trainingPlanGroup?->plan?->course?->title ?? $payment->enrollment?->form?->course?->title;
        $body = collect([
            $this->money($payment->amount).' encaissés',
            $course,
            $payment->reference ? 'Réf. '.$payment->reference : null,
            $payment->remaining_balance !== null ? 'Reste '.$this->money($payment->remaining_balance) : null,
        ])->filter()->implode(' • ');
        foreach ($student?->parents?->pluck('user')->filter()->unique('id') ?? [] as $parent) {
            $this->send($parent->id, 'payment.student_validated', 'Paiement validé : '.$student->full_name,
                $body, $payment, ['url' => '/portal/children/'.$payment->student_id, 'amount' => $payment->amount]);
        }
    }

    private function salaryGenerated(SalaryStatement $statement): void
    {
        $statement->loadMissing('staff');
        if (! $statement->staff?->user_id) {
            return;
        }

        $period = $statement->period_start && $statement->period_end
            ? 'Du '.$statement->period_start->format('d/m/Y').' au '.$statement->period_end->format('d/m/Y') : null;
        $body = collect([$period, 'Net '.$this->money($statement->net_salary), 'Reste '.$this->money($statement->remaining_amount)])->filter()->implode(' • ');
        $this->send($statement->staff->user_id, 'salary.generated', 'Bulletin disponible : '.$statement->reference,
            $body, $statement, ['url' => '/portal/payments', 'reference' => $statement->reference]);
    }

    private function salaryPaid(SalaryPayment $payment): void
    {
        $payment->loadMissing(['staff', 'statement']);
        if (! $payment->staff?->user_id) {
            return;
        }

        $body = collect([
            $this->money($payment->amount),
            $payment->paid_at ? 'Le '.$payment->paid_at->format('d/m/Y à H:i') : null,
            $payment->reference ? 'Réf. '.$payment->reference : null,
            $payment->statement?->reference ? 'Bulletin '.$payment->statement->reference : null,
        ])->filter()->implode(' • ');
        $this->send($payment->staff->user_id, 'salary.paid', 'Salaire payé : '.$this->money($payment->amount),
            $body, $payment, ['url' => '/portal/payments', 'amount' => $payment->amount]);
    }

    private function studentAssigned(CourseEnrollment $enrollment, bool $moved): void
    {
        if (! $enrollment->student_id || ! $enrollment->training_plan_group_id) {
            return;
        }

        $enrollment->loadMissing(['student.parents.user', 'trainingPlanGroup.plan.course']);
        $student = $enrollment->student;
        $group = $enrollment->trainingPlanGroup;
        $body = collect([$group?->plan?->title, $group?->plan?->course?->title, $group?->name])->filter()->implode(' • ');
        foreach ($student?->parents?->pluck('user')->filter()->unique('id') ?? [] as $parent) {
            $this->send($parent->id, $moved ? 'student.group_changed' : 'student.plan_assigned',
                $moved ? 'Nouveau groupe pour '.$student->full_name : 'Nouvelle formation pour '.$student->full_name,
                $body, $enrollment, ['url' => '/portal/children/'.$student->id, 'group' => $group?->name]);
        }
    }

    private function attendanceStatus(?string $status): string
    {
        return match ($status) {
            'present' => 'Présent',
            'absent' => 'Absent',
            'late' => 'En retard',
            'excused' => 'Excusé',
            'left_early' => 'Départ anticipé',
            'replaced' => 'Remplacé',
            'cancelled' => 'Annulé',
            default => ucfirst((string) $status),
        };
    }

    private function dateTime($date): ?string
    {
        return $date?->translatedFormat('d/m/Y à H:i');
    }

    private function money($amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' DA';
    }
}
