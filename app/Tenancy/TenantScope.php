<?php

namespace App\Tenancy;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class TenantScope
{
    public const MODELS = [
        \App\Models\AnnualLeave::class, \App\Models\AnnualLeaveEvent::class,
        \App\Models\AttendanceHistory::class, \App\Models\AuditLog::class,
        \App\Models\Badge::class, \App\Models\BadgeTemplate::class,
        \App\Models\Car::class, \App\Models\Certificate::class,
        \App\Models\Classroom::class, \App\Models\CompanySetting::class,
        \App\Models\Course::class, \App\Models\Formation::class, \App\Models\SchoolSubject::class, \App\Models\CourseEnrollment::class,
        \App\Models\CourseLevel::class, \App\Models\Driver::class,
        \App\Models\EmployeeAttendance::class, \App\Models\EmployeeDocument::class,
        \App\Models\EmployeeHrRecord::class, \App\Models\EmployeeHrRecordEvent::class,
        \App\Models\EmployeeType::class, \App\Models\EnrollmentFinancialAdjustment::class,
        \App\Models\EnrollmentForm::class, \App\Models\EnrollmentHistory::class,
        \App\Models\Expense::class, \App\Models\FcmToken::class,
        \App\Models\FuelTankRecord::class, \App\Models\LeaveBalanceAdjustment::class,
        \App\Models\Message::class, \App\Models\Payment::class,
        \App\Models\PortalNotification::class, \App\Models\Reservation::class,
        \App\Models\SalaryAdjustment::class, \App\Models\SalaryConfiguration::class,
        \App\Models\SalaryItem::class, \App\Models\SalaryStatementLine::class,
        \App\Models\PayrollRegulation::class, \App\Models\PayrollDeclaration::class, \App\Models\SalaryStatutoryLine::class,
        \App\Models\SalaryPayment::class, \App\Models\SalaryStatement::class,
        \App\Models\SchoolParent::class, \App\Models\SchoolSite::class,
        \App\Models\PrivateSchoolInscriptionCampaign::class, \App\Models\PrivateSchoolCampaignLevel::class, \App\Models\PrivateSchoolInscription::class,
        \App\Models\AcademicYear::class, \App\Models\AcademicYearCalendarEvent::class, \App\Models\AttendanceException::class, \App\Models\StudentAcademicEnrollment::class, \App\Models\TeacherAcademicAssignment::class,
        \App\Models\SchoolCycle::class, \App\Models\SchoolLevel::class, \App\Models\SchoolGroup::class, \App\Models\SchoolStream::class,
        \App\Models\AcademicPeriod::class, \App\Models\TimetableSession::class,
        \App\Models\RoomReservation::class, \App\Models\TeacherAvailability::class,
        \App\Models\TeacherUnavailablePeriod::class, \App\Models\TimetableSetting::class,
        \App\Models\SessionAttendance::class, \App\Models\SickLeave::class,
        \App\Models\SickLeaveEvent::class, \App\Models\Staff::class,
        \App\Models\Student::class, \App\Models\StudentHistory::class,
        \App\Models\StudentInstallment::class, \App\Models\StudentObservation::class,
        \App\Models\StudentPayment::class, \App\Models\TeacherAttendance::class,
        \App\Models\Ticket::class, \App\Models\TrainingPlan::class,
        \App\Models\TrainingPlanGroup::class, \App\Models\TrainingPlanTeacherAccess::class,
        \App\Models\TrainingSession::class, \App\Models\User::class,
        \MohamedGaldi\ViltFilepond\Models\File::class,
        \MohamedGaldi\ViltFilepond\Models\TempFile::class,
    ];

    public static function boot(string $modelClass): void
    {
        $modelClass::addGlobalScope('tenant', function (Builder $builder): void {
            if ($tenantId = app(TenantContext::class)->id()) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        $modelClass::creating(function (Model $model): void {
            if ($model instanceof User) {
                $role = $model->getAttribute('role');
                if ($role === UserRole::SUPER_ADMIN) {
                    return;
                }
                if (($role === UserRole::PARENT || $role === UserRole::PARENT->value) && array_key_exists('tenant_id', $model->getAttributes())) {
                    return;
                }
            }
            if ($model->getAttribute('tenant_id')) {
                return;
            }
            $tenantId = app(TenantContext::class)->id();
            if (! $tenantId && app()->runningInConsole()) {
                $tenantId = Tenant::query()->value('id');
            }
            if (! $tenantId) {
                throw new LogicException('A tenant context is required to create '.get_class($model).'.');
            }
            $model->setAttribute('tenant_id', $tenantId);
        });

        $modelClass::resolveRelationUsing('tenant', fn (Model $model): BelongsTo => $model->belongsTo(Tenant::class));
    }
}
