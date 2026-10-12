<?php

namespace App\Tenancy;

use App\Models\Tenant;

final class TenantContext
{
    private ?int $tenantId = null;

    public function set(Tenant|int $tenant): void
    {
        $this->tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function check(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    /** Run a callback inside a tenant, restoring the previous context afterwards. */
    public function runAs(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenant);
        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
