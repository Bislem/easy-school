<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }
        DB::table('permissions')->updateOrInsert(['key' => 'formation_fees.manage'], ['name' => 'Gérer les tarifs de formation', 'updated_at' => now(), 'created_at' => now()]);
        $permissionId = DB::table('permissions')->where('key', 'formation_fees.manage')->value('id');
        DB::table('roles')->whereIn('system_key', ['tenant_administrator', 'finance'])->orderBy('id')->each(function ($role) use ($permissionId): void {
            DB::table('role_permission')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $role->id, 'tenant_id' => $role->tenant_id]);
        });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'formation_fees.manage')->value('id');
        DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
