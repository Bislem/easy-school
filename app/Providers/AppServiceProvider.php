<?php

namespace App\Providers;

use App\Enums\StaffPermission;
use App\Enums\BadgePermission;
use App\Enums\ManagementPermission;
use App\Enums\AttendancePermission;
use App\Models\AuditLog;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\EnrollmentFinancialAdjustment;
use App\Models\SalaryAdjustment;
use App\Models\SalaryPayment;
use App\Models\StudentHistory;
use App\Models\StudentPayment;
use App\Models\TrainingSession;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanTeacherAccess;
use App\Models\SessionAttendance;
use App\Models\TeacherAttendance;
use App\Models\SalaryStatement;
use App\Models\CourseEnrollment;
use App\Observers\PortalNotificationObserver;
use App\Models\Staff;
use App\Models\User;
use App\Policies\StaffPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Staff::class, StaffPolicy::class);
        foreach (StaffPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->role->value === 'admin');
        }
        foreach (BadgePermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->role->value === 'admin');
        }
        foreach (ManagementPermission::cases() as $permission) Gate::define($permission->value, fn(User $user)=>$user->role->value==='admin');
        foreach (AttendancePermission::cases() as $permission) Gate::define($permission->value, fn(User $user)=>$user->role->value==='admin');
        $created=[StudentPayment::class=>'student_payment.recorded',SalaryPayment::class=>'salary_payment.recorded',SalaryAdjustment::class=>'salary_adjustment.recorded',EnrollmentFinancialAdjustment::class=>'student_finance.adjusted',StudentHistory::class=>'student_history.recorded',Certificate::class=>'certificate.issued'];
        foreach($created as $model=>$event)$model::created(fn($item)=>self::audit($event,$item,null,$item->getAttributes()));
        TrainingSession::updated(fn($item)=>self::audit('session.changed',$item,$item->getOriginal(),$item->getChanges()));
        Badge::updated(fn($item)=>self::audit('badge.changed',$item,$item->getOriginal(),$item->getChanges()));
        foreach ([TrainingPlan::class, TrainingPlanTeacherAccess::class, TrainingSession::class, SessionAttendance::class, TeacherAttendance::class, StudentPayment::class, SalaryStatement::class, SalaryPayment::class, CourseEnrollment::class] as $model) {
            $model::observe(PortalNotificationObserver::class);
        }
    }

    private static function audit(string $event,$related,?array $old,?array $new): void
    {
        foreach(['verification_token','password','remember_token'] as $sensitive){if($old)unset($old[$sensitive]);if($new)unset($new[$sensitive]);}
        $request=request();AuditLog::create(['user_id'=>auth()->id(),'event'=>$event,'related_type'=>$related->getMorphClass(),'related_id'=>$related->getKey(),'description'=>$event,'old_values'=>$old,'new_values'=>$new,'ip_address'=>$request?->ip(),'user_agent'=>substr((string)$request?->userAgent(),0,500),'occurred_at'=>now()]);
    }
}
