<?php

namespace App\Providers;

use App\Enums\AcademicYearPermission;
use App\Enums\AttendancePermission;
use App\Enums\BadgePermission;
use App\Enums\ManagementPermission;
use App\Enums\SchoolAttendancePermission;
use App\Enums\StaffPermission;
use App\Enums\TimetablePermission;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentFinancialAdjustment;
use App\Models\SalaryAdjustment;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use App\Models\SessionAttendance;
use App\Models\Staff;
use App\Models\StudentHistory;
use App\Models\StudentPayment;
use App\Models\TeacherAttendance;
use App\Models\TimetableSession;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanTeacherAccess;
use App\Models\TrainingSession;
use App\Models\User;
use App\Observers\PortalNotificationObserver;
use App\Policies\StaffPolicy;
use App\Policies\TimetableSessionPolicy;
use App\Support\PermissionCatalog;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use App\Services\TenantFilePondService;
use App\Services\TenantStorageService;
use App\Services\AuthorizationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(\MohamedGaldi\ViltFilepond\Services\FilePondService::class, TenantFilePondService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (TenantScope::MODELS as $model) {
            TenantScope::boot($model);
        }
        foreach ([\App\Models\Student::class, \App\Models\SchoolParent::class, \App\Models\SchoolGroup::class,
            \App\Models\Staff::class, \App\Models\StudentAcademicEnrollment::class, \App\Models\CourseEnrollment::class,
            \App\Models\StudentPayment::class, \App\Models\StudentObservation::class, \App\Models\SessionAttendance::class,
            \App\Models\TimetableSession::class, \App\Models\Expense::class, \App\Models\SalaryStatement::class,
            \App\Models\SalaryPayment::class] as $scopedModel) {
            $scopedModel::addGlobalScope(AuthorizationService::DATA_SCOPE, function ($query): void {
                $permission = request()?->attributes->get('effective_permission');
                if ($permission && request()->user()) app(AuthorizationService::class)->apply($query, request()->user(), $permission);
            });
        }
        \MohamedGaldi\ViltFilepond\Models\File::deleted(function ($file): void {
            $tenantId = $file->fileable?->tenant_id;
            if ($tenantId) {
                app(TenantStorageService::class)->forget($file->getCleanPath(), config('vilt-filepond.storage_disk'), \App\Models\Tenant::findOrFail($tenantId));
            }
        });
        Gate::policy(Staff::class, StaffPolicy::class);
        Gate::policy(TimetableSession::class, TimetableSessionPolicy::class);
        Gate::before(fn (User $user, string $ability) => $user->hasPermission($ability) ? true : null);
        foreach (PermissionCatalog::all() as $permission => $label) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
        foreach (StaffPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }
        foreach (BadgePermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }
        foreach (ManagementPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }
        foreach (AttendancePermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }
        foreach (TimetablePermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission->value));
        }
        foreach (AcademicYearPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->tenant?->organization_type === 'private_school' && $user->hasPermission($permission->value));
        }
        foreach (SchoolAttendancePermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->tenant?->organization_type === 'private_school' && $user->hasPermission($permission->value));
        }
        $created = [StudentPayment::class => 'student_payment.recorded', SalaryPayment::class => 'salary_payment.recorded', SalaryAdjustment::class => 'salary_adjustment.recorded', EnrollmentFinancialAdjustment::class => 'student_finance.adjusted', StudentHistory::class => 'student_history.recorded', Certificate::class => 'certificate.issued'];
        foreach ($created as $model => $event) {
            $model::created(fn ($item) => self::audit($event, $item, null, $item->getAttributes()));
        }
        TrainingSession::updated(fn ($item) => self::audit('session.changed', $item, $item->getOriginal(), $item->getChanges()));
        Badge::updated(fn ($item) => self::audit('badge.changed', $item, $item->getOriginal(), $item->getChanges()));
        foreach ([TrainingPlan::class, TrainingPlanTeacherAccess::class, TrainingSession::class, SessionAttendance::class, TeacherAttendance::class, StudentPayment::class, SalaryStatement::class, SalaryPayment::class, CourseEnrollment::class] as $model) {
            $model::observe(PortalNotificationObserver::class);
        }
    }

    private static function audit(string $event, $related, ?array $old, ?array $new): void
    {
        foreach (['verification_token', 'password', 'remember_token'] as $sensitive) {
            if ($old) {
                unset($old[$sensitive]);
            }if ($new) {
                unset($new[$sensitive]);
            }
        }
        $request = request();
        AuditLog::create(['user_id' => auth()->id(), 'event' => $event, 'related_type' => $related->getMorphClass(), 'related_id' => $related->getKey(), 'description' => $event, 'old_values' => $old, 'new_values' => $new, 'ip_address' => $request?->ip(), 'user_agent' => substr((string) $request?->userAgent(), 0, 500), 'occurred_at' => now()]);
    }
}
