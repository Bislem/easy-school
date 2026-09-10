<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantStorageService;
use Illuminate\Console\Command;

class RepairTenantStorage extends Command
{
    protected $signature = 'tenants:repair-storage {tenant? : Tenant ID}';
    protected $description = 'Recalculate tenant storage counters from tracked file metadata';

    public function handle(TenantStorageService $storage): int
    {
        $tenant = $this->argument('tenant') ? Tenant::findOrFail((int) $this->argument('tenant')) : null;
        $count = $storage->repair($tenant);
        $this->info("Recalculated storage usage for {$count} tenant(s).");
        return self::SUCCESS;
    }
}
