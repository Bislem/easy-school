<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Services\AlgerianCurriculum2026;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class AlgerianCurriculum2026Seeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);
        Tenant::query()->each(function (Tenant $tenant) use ($context): void {
            try {
                $context->set($tenant);
                app(AlgerianCurriculum2026::class)->initialize();
            } finally {
                $context->clear();
            }
        });
    }
}
