<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\DefaultTenantRoles;
use Illuminate\Console\Command;

class ProvisionTenantRoles extends Command
{
    protected $signature = 'rbac:seed-default-roles {--tenant= : Provision only this tenant ID}';

    protected $description = 'Idempotently provision centrally defined permissions and default tenant role templates';

    public function handle(DefaultTenantRoles $roles): int
    {
        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $roles->provision(Tenant::withoutGlobalScopes()->findOrFail($tenantId));
            $this->info("RBAC roles provisioned for tenant {$tenantId}.");
        } else {
            $roles->provisionAllTenants();
            $this->info('RBAC roles provisioned for all tenants.');
        }

        return self::SUCCESS;
    }
}
