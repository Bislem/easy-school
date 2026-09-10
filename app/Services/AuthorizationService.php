<?php

namespace App\Services;

use App\Enums\DataScope;
use App\Enums\UserRole;
use App\Models\SchoolGroup;
use App\Models\SchoolParent;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\StudentObservation;
use App\Models\StudentPayment;
use App\Models\CourseEnrollment;
use App\Models\Expense;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use App\Models\SessionAttendance;
use App\Models\TimetableSession;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** The single application boundary for effective permissions and record scope. */
final class AuthorizationService
{
    public const DATA_SCOPE = 'effective_data_scope';
    /** @return list<string> */
    public function permissions(User $user): array
    {
        if ($user->role === UserRole::SUPER_ADMIN) return [];

        if ($user->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR)) {
            return array_keys(PermissionCatalog::all());
        }

        return $user->roles()->where('roles.is_active', true)
            ->with('permissions:id,key')->get()->flatMap->permissions
            ->pluck('key')->map(fn (string $key) => PermissionCatalog::normalize($key))
            ->unique()->sort()->values()->all();
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array(PermissionCatalog::normalize($permission), $this->permissions($user), true);
    }

    public function scope(User $user, string $permission): ?DataScope
    {
        if ($user->hasSystemRole(DefaultTenantRoles::TENANT_ADMINISTRATOR)) {
            return DataScope::TENANT;
        }
        $permission = PermissionCatalog::normalize($permission);
        $scopes = $user->roles()->where('roles.is_active', true)
            ->whereHas('permissions', fn ($q) => $q->where('key', $permission))
            ->pluck('role_user.data_scope')->filter()
            ->map(fn (string $scope) => DataScope::tryFrom($scope))->filter();

        return $scopes->sortByDesc->rank()->first();
    }

    /** Apply the user's independent data scope to a supported resource query. */
    public function apply(Builder $query, User $user, string $permission): Builder
    {
        abort_unless($this->allows($user, $permission), 403, 'Forbidden.');
        $scope = $this->scope($user, $permission);
        abort_unless($scope, 403, 'Forbidden.');
        if ($scope === DataScope::TENANT) return $query;

        $model = $query->getModel();
        if ($scope === DataScope::OWN) return $this->own($query, $model, $user);
        if ($scope === DataScope::ASSIGNED) return $this->assigned($query, $model, $user);
        return $this->site($query, $model, $user);
    }

    public function authorizeRecord(User $user, string $permission, Model $record): void
    {
        $visible = $this->apply($record->newQuery(), $user, $permission)->whereKey($record->getKey())->exists();
        abort_unless($visible, 403, 'Forbidden.');
    }

    /** Map every protected route to a stable permission; unknown routes are denied. */
    public function permissionForRoute(?string $name, string $method): ?string
    {
        if (! $name) return null;
        $write = ! in_array($method, ['GET', 'HEAD'], true);
        $create = $method === 'POST';
        $delete = $method === 'DELETE';
        $rules = [
            'admin.students.' => $delete ? 'students.delete' : ($create ? 'students.create' : ($write ? 'students.update' : 'students.view')),
            'admin.parents.' => $delete ? 'parents.delete' : ($create ? 'parents.create' : ($write ? 'parents.update' : 'parents.view')),
            'admin.groups.' => $write ? 'groups.manage' : 'groups.view',
            'admin.school-levels.' => 'groups.manage',
            'admin.timetable' => $write ? 'timetables.manage' : 'timetables.view',
            'admin.school-attendance.' => $write ? 'student_attendance.record' : 'student_attendance.view',
            'admin.attendance.' => $write ? 'staff_attendance.record' : 'staff_attendance.view',
            'admin.staff.' => $delete ? 'employees.delete' : ($create ? 'employees.create' : ($write ? 'employees.update' : 'employees.view')),
            'admin.users.' => $write ? 'users.manage' : 'users.view',
            'admin.salaries.' => $write ? 'salaries.manage' : 'salaries.view',
            'admin.finance.' => $write ? 'payments.collect' : 'payments.view',
            'admin.expenses.' => $delete ? 'expenses.delete' : ($create ? 'expenses.create' : ($write ? 'expenses.update' : 'expenses.view')),
            'admin.enrollment-forms.' => $write ? 'enrollments.manage' : 'enrollments.view',
            'admin.training-plans.' => $write ? 'timetables.manage' : 'timetables.view',
            'admin.plans.' => $write ? 'timetables.manage' : 'timetables.view',
            'admin.registrations.' => $write ? 'enrollments.manage' : 'enrollments.view',
            'admin.reports.' => str_contains($name, '.export') ? 'reports.export' : 'reports.view',
            'admin.school-documents.' => $write ? 'administrative_documents.manage' : 'administrative_documents.view',
            'admin.academic-years.' => $write ? 'academic_years.manage' : 'academic_years.view',
            'admin.badges.' => $write ? 'badges.manage' : 'badges.view',
            'admin.certificates.' => $write ? 'certificates.manage' : 'certificates.view',
            'admin.audit.' => 'audit.view',
            'admin.sites.' => $write ? 'groups.manage' : 'groups.view',
            'admin.classrooms.' => $write ? 'groups.manage' : 'groups.view',
            'admin.courses.' => $write ? 'groups.manage' : 'groups.view',
            'admin.subjects.' => $write ? 'groups.manage' : 'groups.view',
            'admin.private-school-inscriptions.' => $write ? 'enrollments.manage' : 'enrollments.view',
            'admin.private-school-campaigns.' => $write ? 'enrollments.manage' : 'enrollments.view',
            'admin.notifications.' => 'users.manage',
            'admin.access.' => 'roles.view',
        ];
        $exact = [
            'admin.finance.payments.reverse' => 'payments.cancel',
            'admin.finance.payments.refund' => 'payments.refund',
            'admin.finance.payments.receipt' => 'payments.view',
            'admin.salaries.generate' => 'salaries.approve',
            'admin.salaries.declarations.declared' => 'salaries.approve',
            'admin.salaries.print' => 'salaries.export',
            'admin.salaries.payments.receipt' => 'salaries.view',
            'admin.reports.export' => 'reports.export',
            'admin.school-attendance.reports.export' => 'reports.export',
            'admin.settings.edit' => 'users.view', 'admin.settings.update' => 'users.manage',
            'admin.account.index' => 'users.view',
            'admin.users.roles.index' => 'roles.view',
            'admin.users.roles.store' => 'roles.manage',
            'admin.users.roles.update' => 'roles.manage',
            'admin.users.roles.destroy' => 'roles.manage',
            'admin.users.roles.assign' => 'roles.manage',
            'admin.users.roles.duplicate' => 'roles.manage',
            'admin.users.roles.toggle' => 'roles.manage',
            'admin.users.roles.restore' => 'roles.manage',
        ];
        if (isset($exact[$name])) return $exact[$name];
        foreach ($rules as $prefix => $permission) if (str_starts_with($name, $prefix)) return $permission;
        return null;
    }

    private function own(Builder $query, Model $model, User $user): Builder
    {
        return match (true) {
            $model instanceof User => $query->whereKey($user->id),
            $model instanceof Staff => $query->where('user_id', $user->id),
            $model instanceof Student => $query->where('user_id', $user->id),
            $model instanceof SchoolParent => $query->where('user_id', $user->id),
            $model instanceof StudentObservation => $query->where('author_id', $user->id),
            $model instanceof StudentAcademicEnrollment, $model instanceof CourseEnrollment,
            $model instanceof StudentPayment, $model instanceof SessionAttendance => $query->whereHas('student', fn ($q) => $q->where('user_id', $user->id)),
            $model instanceof SalaryStatement, $model instanceof SalaryPayment => $query->whereHas('staff', fn ($q) => $q->where('user_id', $user->id)),
            $model instanceof Expense => $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhereHas('staff', fn ($s) => $s->where('user_id', $user->id))),
            $model instanceof TimetableSession => $query->where('teacher_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function assigned(Builder $query, Model $model, User $user): Builder
    {
        $groupIds = $user->schoolGroups()->withoutGlobalScope(self::DATA_SCOPE)->pluck('school_groups.id')
            ->merge($user->principalGroups()->withoutGlobalScope(self::DATA_SCOPE)->pluck('id'))->unique();
        return match (true) {
            $model instanceof SchoolGroup => $query->whereKey($groupIds),
            $model instanceof Student => $query->where(fn ($q) => $q->whereIn('school_group_id', $groupIds)
                ->orWhereHas('academicEnrollments', fn ($e) => $e->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_group_id', $groupIds))),
            $model instanceof SchoolParent => $query->whereHas('students', fn ($q) => $this->assigned($q->withoutGlobalScope(self::DATA_SCOPE), $q->getModel(), $user)),
            $model instanceof StudentAcademicEnrollment => $query->whereIn('school_group_id', $groupIds),
            $model instanceof CourseEnrollment => $query->whereHas('student', fn ($q) => $this->assigned($q->withoutGlobalScope(self::DATA_SCOPE), $q->getModel(), $user)),
            $model instanceof StudentPayment, $model instanceof SessionAttendance, $model instanceof StudentObservation => $query->whereHas('student', fn ($q) => $this->assigned($q->withoutGlobalScope(self::DATA_SCOPE), $q->getModel(), $user)),
            $model instanceof TimetableSession => $query->where(fn ($q) => $q->whereIn('school_group_id', $groupIds)->orWhere('teacher_id', $user->id)),
            $model instanceof Staff => $this->own($query, $model, $user),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function site(Builder $query, Model $model, User $user): Builder
    {
        $siteIds = $user->schoolSites()->pluck('school_sites.id');
        return match (true) {
            $model instanceof SchoolGroup => $query->whereHas('classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof Student => $query->whereHas('group.classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof SchoolParent => $query->whereHas('students.group.classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof StudentAcademicEnrollment => $query->whereHas('group.classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof CourseEnrollment => $query->whereHas('student.group.classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof StudentPayment, $model instanceof SessionAttendance, $model instanceof StudentObservation => $query->whereHas('student.group.classroom', fn ($q) => $q->withoutGlobalScope(self::DATA_SCOPE)->whereIn('school_site_id', $siteIds)),
            $model instanceof TimetableSession => $query->whereHas('room', fn ($q) => $q->whereIn('school_site_id', $siteIds)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
