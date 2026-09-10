<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class DefaultTenantRoles
{
    public const TENANT_ADMINISTRATOR = 'tenant_administrator';

    /** @return array<string, array{name: string, permissions: list<string>}> */
    public static function templates(): array
    {
        $all = array_keys(PermissionCatalog::all());

        return [
            self::TENANT_ADMINISTRATOR => ['name' => 'Tenant Administrator', 'permissions' => $all],
            'hr' => ['name' => 'RH', 'permissions' => self::matching(['employees.', 'staff_attendance.', 'salaries.', 'payslips.', 'hr_records.'])],
            'finance' => ['name' => 'Finance', 'permissions' => self::matching(['payments.', 'school_fees.', 'expenses.', 'financial_reports.'])],
            'general_supervision' => ['name' => 'Surveillance générale', 'permissions' => self::matching(['students.', 'parents.', 'groups.', 'timetables.', 'student_attendance.', 'absences.', 'discipline.', 'observations.'])],
            'teacher' => ['name' => 'Teacher', 'permissions' => self::matching(['assigned_groups.', 'timetables.view', 'student_attendance.view', 'student_attendance.record', 'grades.', 'homework.', 'observations.'])],
            'reception_registration' => ['name' => 'Reception/Registration', 'permissions' => self::matching(['enrollments.', 'students.view', 'students.create', 'students.update', 'parents.', 'administrative_documents.'])],
        ];
    }

    public function provision(Tenant $tenant): void
    {
        $context = app(TenantContext::class);
        $previousTenantId = $context->id();
        $context->set($tenant);

        try {
            DB::transaction(function () use ($tenant): void {
                $permissions = collect(PermissionCatalog::all())->mapWithKeys(function (string $name, string $key) {
                    $permission = Permission::updateOrCreate(['key' => $key], ['name' => $name]);

                    return [$key => $permission->id];
                });
                foreach (self::templates() as $systemKey => $template) {
                    $role = Role::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'system_key' => $systemKey],
                        ['name' => $template['name'], 'is_active' => true],
                    );
                    if ($role->wasRecentlyCreated) {
                        $role->permissions()->syncWithPivotValues($permissions->only($template['permissions'])->values(), ['tenant_id' => $tenant->id]);
                    }
                }

                $administrator = Role::where('system_key', self::TENANT_ADMINISTRATOR)->firstOrFail();
                User::where('role', 'admin')->get()->each(fn (User $user) => $administrator->users()->syncWithoutDetaching([$user->id => ['tenant_id' => $tenant->id]]));
            });
        } finally {
            $previousTenantId ? $context->set($previousTenantId) : $context->clear();
        }
    }

    public function provisionAllTenants(): void
    {
        Tenant::withoutGlobalScopes()->orderBy('id')->each(fn (Tenant $tenant) => $this->provision($tenant));
    }

    /** @param list<string> $prefixes */
    private static function matching(array $prefixes): array
    {
        return array_values(array_filter(array_keys(PermissionCatalog::all()), fn (string $key) => collect($prefixes)->contains(fn (string $prefix) => str_ends_with($prefix, '.') ? str_starts_with($key, $prefix) : $key === $prefix)));
    }
}
