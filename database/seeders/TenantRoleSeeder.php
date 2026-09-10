<?php

namespace Database\Seeders;

use App\Services\DefaultTenantRoles;
use Illuminate\Database\Seeder;

class TenantRoleSeeder extends Seeder
{
    public function run(DefaultTenantRoles $roles): void
    {
        $roles->provisionAllTenants();
    }
}
