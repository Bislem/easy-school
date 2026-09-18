<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $keys = [
            'absences.view', 'absences.create', 'absences.update', 'absences.delete',
            'student_absences.manage', 'teacher_absences.manage', 'absence_justifications.manage',
        ];
        $permissions = collect($keys)->mapWithKeys(function (string $key): array {
            $permission = Permission::updateOrCreate(['key' => $key], ['name' => PermissionCatalog::all()[$key]]);

            return [$key => $permission->id];
        });

        Role::withoutGlobalScopes()->with('permissions:id,key')->get()->each(function (Role $role) use ($permissions): void {
            $legacy = $role->permissions->pluck('key');
            $keys = match (true) {
                $role->system_key === 'tenant_administrator' => $permissions->keys(),
                $legacy->contains('absences.manage'), $legacy->contains('student_attendance.record') => collect([
                    'absences.view', 'absences.create', 'absences.update', 'absences.delete',
                    'student_absences.manage', 'teacher_absences.manage', 'absence_justifications.manage',
                ]),
                $legacy->contains('absences.view'), $legacy->contains('student_attendance.view') => collect(['absences.view']),
                default => collect(),
            };
            foreach ($keys as $key) {
                DB::table('role_permission')->updateOrInsert(
                    ['role_id' => $role->id, 'permission_id' => $permissions[$key]],
                    ['tenant_id' => $role->tenant_id, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    public function down(): void
    {
        $ids = Permission::whereIn('key', [
            'absences.create', 'absences.update', 'absences.delete', 'student_absences.manage',
            'teacher_absences.manage', 'absence_justifications.manage',
        ])->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();
    }
};
